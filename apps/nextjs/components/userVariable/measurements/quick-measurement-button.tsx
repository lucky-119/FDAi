"use client"

import * as React from "react"
import { useRouter } from "next/navigation"

import { Button, ButtonProps } from "@/components/ui/button"
import { toast } from "@/components/ui/use-toast"
import { Icons } from "@/components/icons"
import { UserVariable } from "@/types/models/UserVariable";

interface QuickMeasurementButtonProps extends ButtonProps {
  userVariable: Pick<
    UserVariable,
    "id" | "name" | "description" | "createdAt" | "imageUrl" |
    "combinationOperation" | "unitAbbreviatedName" | "variableCategoryName" |
    "lastValue" | "unitName"
  >
}

export function QuickMeasurementButton({ userVariable, ...props }: QuickMeasurementButtonProps) {
  const router = useRouter()
  const [isLoading, setIsLoading] = React.useState<boolean>(false)

  async function onClick() {
    setIsLoading(true)

    const dateToday = new Date()
    dateToday.setHours(0, 0, 0, 0)

    const response = await fetch(`/api/dfda/measurements/post`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        startAt: dateToday,
        value: userVariable.lastValue,
        variableId: userVariable.name,
        variableCategoryName: userVariable.variableCategoryName,
        combinationOperation: userVariable.combinationOperation,
        unitAbbreviatedName: userVariable.unitAbbreviatedName,
      }),
    })

    if (!response?.ok) {
      toast({
        title: "Something went wrong.",
        description: "Your userVariable was not measured. Please try again.",
        variant: "destructive",
      })
    } else {
      toast({
        description: "Your userVariable has been measured successfully.",
      })
    }

    setIsLoading(false)

    router.refresh()
  }

  return (
    <Button onClick={onClick} disabled={isLoading} {...props}>
      {isLoading ? (
        <Icons.spinner className="h-4 w-4 animate-spin" />
      ) : (
        <>
          <Icons.add className="h-4 w-4" />
          <span>{userVariable.lastValue} {userVariable.unitAbbreviatedName}</span>
        </>
      )}
    </Button>
  )
}
