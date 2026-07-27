<?php

declare(strict_types=1);

namespace App\Traits;

trait TranslatesValidationAttributes
{
    protected function validationAttribute(string $attribute): string
    {
        $attributes = __('validation.attributes');

        if (is_array($attributes) && isset($attributes[$attribute]) && is_string($attributes[$attribute])) {
            return $attributes[$attribute];
        }

        $translationKey = "validation.attributes.{$attribute}";
        $translated = __($translationKey);

        return $translated;
    }
}
