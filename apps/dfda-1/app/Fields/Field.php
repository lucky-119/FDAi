<?php
/*
*  GNU General Public License v3.0
*  Contributors: ADD YOUR NAME HERE, Mike P. Sinn
 */

namespace App\Fields;

use App\Logging\QMLog;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use App\Contracts\Resolvable;
use App\Http\Requests\AstralRequest;

abstract class Field extends FieldElement implements JsonSerializable, Resolvable
{
    use Macroable;

    /**
     * The displayable name of the field.
     *
     * @var string
     */
    public $name;

    /**
     * The attribute / column name of the field.
     *
     * @var string
     */
    public $attribute;

    /**
     * The field's resolved value.
     *
     * @var mixed
     */
    public $value;

    /**
     * The callback to be used to resolve the field's display value.
     *
     * @var \Closure
     */
    public $displayCallback;

    /**
     * The callback to be used to resolve the field's value.
     *
     * @var \Closure
     */
    public $resolveCallback;

    /**
     * The callback to be used to hydrate the model attribute.
     *
     * @var callable
     */
    public $fillCallback;

    /**
     * The callback to be used for computed field.
     *
     * @var callable
     */
    protected $computedCallback;

    /**
     * The validation rules for creation and updates.
     *
     * @var array
     */
    public $rules = [];

    /**
     * The validation rules for creation.
     *
     * @var array
     */
    public $creationRules = [];

    /**
     * The validation rules for updates.
     *
     * @var array
     */
    public $updateRules = [];

    /**
     * Indicates if the field should be sortable.
     *
     * @var bool
     */
    public $sortable = false;

    /**
     * Indicates if the field is nullable.
     *
     * @var bool
     */
    public $nullable = false;

    /**
     * Values which will be replaced to null.
     *
     * @var array
     */
    public $nullValues = [''];

    /**
     * Indicates if the field was resolved as a pivot field.
     *
     * @var bool
     */
    public $pivot = false;

    /**
     * The text alignment for the field's text in tables.
     *
     * @var string
     */
    public $textAlign = 'left';

    /**
     * Indicates if the field label and form element should sit on top of each other.
     *
     * @var bool
     */
    public $stacked = false;

    /**
     * The custom components registered for fields.
     *
     * @var array
     */
    public static $customComponents = [];

    /**
     * The callback used to determine if the field is readonly.
     *
     * @var Closure
     */
    public $readonlyCallback;

    /**
     * The callback used to determine if the field is required.
     *
     * @var Closure
     */
    public $requiredCallback;

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|callable|null  $attribute
     * @param  callable|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, callable $resolveCallback = null)
    {
        $this->name = $name;
        $this->resolveCallback = $resolveCallback;

        if ($attribute instanceof Closure ||
            (is_callable($attribute) && is_object($attribute))) {
            $this->computedCallback = $attribute;
            $this->attribute = 'ComputedField';
        } else {
            $this->attribute = $attribute ?? str_replace(' ', '_', Str::lower($name));
        }
    }

    /**
     * Set the help text for the field.
     *
     * @param  string  $helpText
     * @return $this
     */
    public function help($helpText)
    {
        return $this->withMeta(['helpText' => $helpText]);
    }

    /**
     * Stack the label above the field.
     *
     * @param bool $stack
     *
     * @return $this
     */
    public function stacked($stack = true)
    {
        $this->stacked = $stack;

        return $this;
    }

    /**
     * Resolve the field's value for display.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolveForDisplay($resource, $attribute = null)
    {
        $attribute = $attribute ?? $this->attribute;

        if ($attribute === 'ComputedField') {
            return;
        }

        if (! $this->displayCallback) {
            $this->resolve($resource, $attribute);
        } elseif (is_callable($this->displayCallback)) {
            $resolved = $this->resolveAttribute($resource, $attribute);
            tap($resolved, function ($value) use ($resource, $attribute) {
                $value = call_user_func($this->displayCallback, $value, $resource, $attribute);
                $this->value = $value;
            });
        }
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $attribute = $attribute ?? $this->attribute;

        if ($attribute === 'ComputedField') {
            $this->value = call_user_func($this->computedCallback, $resource);

            return;
        }

        if (! $this->resolveCallback) {
            $this->value = $this->resolveAttribute($resource, $attribute);
        } elseif (is_callable($this->resolveCallback)) {
            tap($this->resolveAttribute($resource, $attribute), function ($value) use ($resource, $attribute) {
                $this->value = call_user_func($this->resolveCallback, $value, $resource, $attribute);
            });
        }
    }

    /**
     * Resolve the given attribute from the given resource.
     *
     * @param  mixed  $resource
     * @param  string  $attribute
     * @return mixed
     */
    protected function resolveAttribute($resource, $attribute)
    {
        return data_get($resource, str_replace('->', '.', $attribute));
    }

    /**
     * Define the callback that should be used to display the field's value.
     *
     * @param  callable  $displayCallback
     * @return $this
     */
    public function displayUsing(callable $displayCallback)
    {
        $this->displayCallback = $displayCallback;

        return $this;
    }

    /**
     * Define the callback that should be used to resolve the field's value.
     *
     * @param  callable  $resolveCallback
     * @return $this
     */
    public function resolveUsing(callable $resolveCallback)
    {
        $this->resolveCallback = $resolveCallback;

        return $this;
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @param  object  $model
     * @return mixed
     */
    public function fill(AstralRequest $request, $model)
    {
        return $this->fillInto($request, $model, $this->attribute);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @param  object  $model
     * @return mixed
     */
    public function fillForAction(AstralRequest $request, $model)
    {
        return $this->fill($request, $model);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @param  object  $model
     * @param  string  $attribute
     * @param  string|null  $requestAttribute
     * @return mixed
     */
    public function fillInto(AstralRequest $request, $model, $attribute, $requestAttribute = null)
    {
        return $this->fillAttribute($request, $requestAttribute ?? $this->attribute, $model, $attribute);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return void
     */
    protected function fillAttribute(AstralRequest $request, $requestAttribute, $model, $attribute)
    {
        if (isset($this->fillCallback)) {
            return call_user_func(
                $this->fillCallback, $request, $model, $attribute, $requestAttribute
            );
        }

        return $this->fillAttributeFromRequest(
            $request, $requestAttribute, $model, $attribute
        );
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return mixed
     */
    protected function fillAttributeFromRequest(AstralRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            $value = $request[$requestAttribute];

            $model->{$attribute} = $this->isNullValue($value) ? null : $value;
        }
    }

    /**
     * Check value for null value.
     *
     * @param  mixed $value
     * @return bool
     */
    protected function isNullValue($value)
    {
        if (! $this->nullable) {
            return false;
        }

        return is_callable($this->nullValues)
            ? ($this->nullValues)($value)
            : in_array($value, (array) $this->nullValues);
    }

    /**
     * Specify a callback that should be used to hydrate the model attribute for the field.
     *
     * @param  callable  $fillCallback
     * @return $this
     */
    public function fillUsing($fillCallback)
    {
        $this->fillCallback = $fillCallback;

        return $this;
    }

    /**
     * Set the validation rules for the field.
     *
     * @param  callable|array|string  $rules
     * @return $this
     */
    public function rules($rules)
    {
        $this->rules = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : $rules;

        return $this;
    }

    /**
     * Get the validation rules for this field.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return array
     */
    public function getRules(AstralRequest $request)
    {
        return [$this->attribute => is_callable($this->rules)
                            ? call_user_func($this->rules, $request)
                            : $this->rules, ];
    }

    /**
     * Get the creation rules for this field.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return array|string
     */
    public function getCreationRules(AstralRequest $request)
    {
        $values = is_callable($this->creationRules)
            ? call_user_func($this->creationRules, $request)
            : $this->creationRules;
        $rules = [$this->attribute => $values];

        return array_merge_recursive(
            $this->getRules($request), $rules
        );
    }

    /**
     * Set the creation validation rules for the field.
     *
     * @param  callable|array|string  $rules
     * @return $this
     */
    public function creationRules($rules)
    {
        $this->creationRules = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : $rules;

        return $this;
    }

    /**
     * Get the update rules for this field.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return array
     */
    public function getUpdateRules(AstralRequest $request)
    {
        $rules = [$this->attribute => is_callable($this->updateRules)
                            ? call_user_func($this->updateRules, $request)
                            : $this->updateRules, ];

        return array_merge_recursive(
            $this->getRules($request), $rules
        );
    }

    /**
     * Set the creation validation rules for the field.
     *
     * @param  callable|array|string  $rules
     * @return $this
     */
    public function updateRules($rules)
    {
        $this->updateRules = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : $rules;

        return $this;
    }

    /**
     * Get the validation attribute for the field.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return string
     */
    public function getValidationAttribute(AstralRequest $request)
    {
        return $this->validationAttribute ?? Str::singular($this->attribute);
    }

    /**
     * Specify that this field should be sortable.
     *
     * @param  bool  $value
     * @return $this
     */
    public function sortable($value = true)
    {
        if (! $this->computed()) {
            $this->sortable = $value;
        }

        return $this;
    }

    /**
     * Return the sortable uri key for the field.
     *
     * @return string
     */
    public function sortableUriKey()
    {
        $request = app(AstralRequest::class);

        switch (get_class($this)) {
            case BelongsTo::class:
                $attr = $this->attribute;
                $newRes = $request->newResource();
                $resource = $newRes->resource;
                $value = $resource->{$attr}();
                return $this->getRelationForeignKeyName($value);
            default:
                return $this->attribute;
        }
    }

    /**
     * Indicate that the field should be nullable.
     *
     * @param  bool  $nullable
     * @param  array|Closure  $values
     * @return $this
     */
    public function nullable($nullable = true, $values = null)
    {
        $this->nullable = $nullable;

        if ($values !== null) {
            $this->nullValues($values);
        }

        return $this;
    }

    /**
     * Specify nullable values.
     *
     * @param  array|Closure  $values
     * @return $this
     */
    public function nullValues($values)
    {
        $this->nullValues = $values;

        return $this;
    }

    /**
     * Determine if the field is computed.
     *
     * @return bool
     */
    public function computed()
    {
        return (is_callable($this->attribute) && ! is_string($this->attribute)) ||
               $this->attribute == 'ComputedField';
    }

    /**
     * Get the component name for the field.
     *
     * @return string
     */
    public function component()
    {
        if (isset(static::$customComponents[get_class($this)])) {
            return static::$customComponents[get_class($this)];
        }

        return $this->component;
    }

    /**
     * Set the component that should be used by the field.
     *
     * @param  string  $component
     * @return void
     */
    public static function useComponent($component)
    {
        static::$customComponents[get_called_class()] = $component;
    }

    /**
     * Set the callback used to determine if the field is readonly.
     *
     * @param  Closure|bool  $callback
     * @return $this
     */
    public function readonly($callback = true)
    {
        $this->readonlyCallback = $callback;

        return $this;
    }

    /**
     * Determine if the field is readonly.
     *
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return bool
     */
    public function isReadonly(AstralRequest $request)
    {
        return with($this->readonlyCallback, function ($callback) use ($request) {
            if ($callback === true || (is_callable($callback) && call_user_func($callback, $request))) {
                $this->setReadonlyAttribute();

                return true;
            }

            return false;
        });
    }

    /**
     * Set the field to a readonly field.
     *
     * @return $this
     */
    protected function setReadonlyAttribute()
    {
        $this->withMeta(['extraAttributes' => ['readonly' => true]]);

        return $this;
    }

    /**
     * Set the text alignment of the field.
     *
     * @param  string  $alignment
     * @return $this
     */
    public function textAlign($alignment)
    {
        $this->textAlign = $alignment;

        return $this;
    }

    /**
     * Set the callback used to determine if the field is required.
     *
     * @param  Closure|bool  $callback
     * @return $this
     */
    public function required($callback = true)
    {
        $this->requiredCallback = $callback;

        return $this;
    }

    /**
     * Determine if the field is required.
     * @param  \App\Http\Requests\AstralRequest  $request
     * @return bool
     */
    public function isRequired(AstralRequest $request): bool
    {
        $required = with($this->requiredCallback, function ($callback) use ($request) {
            if ($callback === true || (is_callable($callback) && call_user_func($callback, $request))) {
                return true;
            }

            $attr = $this->attribute;
            if (! empty($attr) && is_null($callback)) {
                if ($request->isCreateOrAttachRequest()) {
                    $rulesForAll = $this->getCreationRules($request);
                    $rules = $rulesForAll[$attr];
                    return in_array('required', $rules);
                }

                if ($request->isUpdateOrUpdateAttachedRequest()) {
                    return in_array('required', $this->getUpdateRules($request)[$attr]);
                }
            }

            return false;
        });
        return $required;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        try {
            $key = $this->sortableUriKey();
        } catch (\Throwable $e){
            $key = $this->attribute."_id"; // Not sure why this keeps happening
            QMLog::error("Could not get sortableUriKey because:\n".$e->getMessage()."\nso using $key");
        }
        return array_merge([
            'component' => $this->component(),
            'prefixComponent' => true,
            'indexName' => $this->name,
            'name' => $this->name,
            'attribute' => $this->attribute,
            'value' => $this->value,
            'panel' => $this->panel,
            'sortable' => $this->sortable,
            'nullable' => $this->nullable,
            'readonly' => $this->isReadonly(app(AstralRequest::class)),
            'required' => $this->isRequired(app(AstralRequest::class)),
            'textAlign' => $this->textAlign,
            'sortableUriKey' => $key,
            'stacked' => $this->stacked,
        ], $this->meta());
    }
}
