<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

final class LanguagesController extends BaseController
{
    public function index(): never
    {
        $this->requireUser();
        $this->json(['data' => (new \CometCMS\Localization\LanguageSettings())->all()]);
    }
}
