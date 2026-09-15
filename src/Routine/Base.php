<?php

namespace Nails\Housekeeping\Routine;

use Nails\Housekeeping\Interfaces;
use ReflectionClass;

abstract class Base implements Interfaces\Routine
{
    /**
     * Short human label; falls back to DESCRIPTION then the class name
     */
    const LABEL = '';

    /**
     * Description of the routine
     */
    const DESCRIPTION = '';

    /**
     * Cron expression of when to run
     */
    const CRON_EXPRESSION = '';

    /**
     * Environments to run on; empty = every environment
     *
     * @var string[]
     */
    const ENVIRONMENT = [];

    /**
     * Whether the routine should run
     */
    const ENABLED = true;

    public function getKey(): string
    {
        return static::class;
    }

    public function getLabel(): string
    {
        if (static::LABEL !== '') {
            return static::LABEL;
        }

        if (static::DESCRIPTION !== '') {
            return static::DESCRIPTION;
        }

        return (new ReflectionClass($this))->getShortName();
    }

    public function getDescription(): string
    {
        return static::DESCRIPTION;
    }

    public function getCronExpression(): string
    {
        return static::CRON_EXPRESSION;
    }

    /**
     * @return string[]
     */
    public function getEnvironments(): array
    {
        return static::ENVIRONMENT;
    }

    public function isEnabled(): bool
    {
        return static::ENABLED;
    }
}
