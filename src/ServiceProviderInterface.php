<?php

/*
 * This file is part of the guanguans/di.
 *
 * (c) guanguans <ityaozm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Guanguans\Di;

/**
 * `guanguans/di` service provider interface.
 */
interface ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     * This method should only be used to configure services and parameters.
     * It should not get services.
     * @param  \Guanguans\Di\Container  $container A container instance
     * @return mixed
     */
    public function register(Container $container);
}
