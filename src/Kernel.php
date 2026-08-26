<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Application kernel that delegates standard Symfony container and route wiring to MicroKernelTrait.
 *
 * The project intentionally keeps framework bootstrap conventional so domain behavior remains in
 * controllers, forms, entities, repositories and services rather than custom kernel hooks.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
