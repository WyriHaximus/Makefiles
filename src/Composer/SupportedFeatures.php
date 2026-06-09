<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer;

final class SupportedFeatures
{
    public const string FEATURE_CODE_STYLE                   = 'code-style';
    public const string FEATURE_COMPOSER_PLUGIN              = 'composer-plugin';
    public const string FEATURE_COMPOSER_DEPENDENCY_CHECKERS = 'composer-dependency-checkers';
    public const string FEATURE_LINUX                        = 'linux';
    public const string FEATURE_MACOS                        = 'macos';
    public const string FEATURE_STATIC_ANALYSIS              = 'static-analysis';
    public const string FEATURE_UNIT_TESTS                   = 'unit-tests';
    public const string FEATURE_WINDOWS                      = 'windows';
    public const string FEATURE_ZTS                          = 'zts';
    public const array DEFAULTS                              = [
        self::FEATURE_CODE_STYLE => true,
        self::FEATURE_COMPOSER_PLUGIN => false,
        self::FEATURE_COMPOSER_DEPENDENCY_CHECKERS => true,
        self::FEATURE_LINUX => true,
        self::FEATURE_MACOS => true,
        self::FEATURE_STATIC_ANALYSIS => true,
        self::FEATURE_UNIT_TESTS => true,
        self::FEATURE_WINDOWS => true,
        self::FEATURE_ZTS => false,
    ];
}
