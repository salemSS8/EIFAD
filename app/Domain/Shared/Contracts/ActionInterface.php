<?php

namespace App\Domain\Shared\Contracts;

/**
 * Base interface for all action/use case classes.
 * Actions encapsulate business logic and are HTTP-agnostic.
 */
interface ActionInterface
{
    // Marker interface - specific actions define their own execute() signatures
}
