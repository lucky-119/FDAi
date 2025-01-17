<?php
/*
*  GNU General Public License v3.0
*  Contributors: ADD YOUR NAME HERE, Mike P. Sinn
 */

namespace App\Properties\Base;
use App\Models\Application;
use App\Traits\PropertyTraits\IsNumberOfRelated;
use App\UI\ImageUrls;
use App\UI\FontAwesome;
use App\Properties\BaseProperty;
class BaseNumberOfApplicationsWherePredictorVariableProperty extends BaseProperty{
	use IsNumberOfRelated;
	public $dbInput = 'integer,false,true';
	public $dbType = \Doctrine\DBAL\Types\Types::INTEGER;
	public $default = \OpenApi\Generator::UNDEFINED;
	public $description = 'Number of Applications for this Predictor Variable.
                [Formula:
                    update variables
                        left join (
                            select count(id) as total, predictor_variable_id
                            from applications
                            group by predictor_variable_id
                        )
                        as grouped on variables.id = grouped.predictor_variable_id
                    set variables.number_of_applications_where_predictor_variable = count(grouped.total)
                ]
                ';
	public $fieldType = self::TYPE_INTEGER;
	public $fontAwesome = FontAwesome::APPLICATIONS;
	public $htmlType = self::TYPE_NUMBER;
	public $image = ImageUrls::APPLICATIONS;
	public $isOrderable = true;
	public $isSearchable = false;
	public $name = self::NAME;
	public const NAME = 'number_of_applications_where_predictor_variable';
	public $phpType = \App\Types\PhpTypes::INTEGER;
	public $title = 'Applications Where Predictor Variable';
	public $type = self::TYPE_INTEGER;
    protected static function getRelationshipClass(): string{return Application::class;}

}
