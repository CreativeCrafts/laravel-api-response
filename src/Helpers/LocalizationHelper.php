<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\LocalizationHelperContract;
use Illuminate\Support\Facades\Lang;

final readonly class LocalizationHelper implements LocalizationHelperContract
{
    /**
     * Localize a given message using Laravel's translation system.
     * This method takes a message string and attempts to translate it using
     * Laravel's built-in localization function. If a translation is found,
     * it returns the translated string; otherwise, it returns the original message.
     *
     * @param string $message The message to be localized.
     * @return string The localized version of the message if a translation exists,
     *                or the original message if no translation is found.
     */
    public function localize(string $message): string
    {
        if (Lang::has($message)) {
            return __($message);
        }
        return $message;
    }
}
