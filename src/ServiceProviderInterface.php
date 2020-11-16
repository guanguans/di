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
     * 在给定的容器上注册服务。此方法仅应用于配置服务和参数。它不应获得服务。
     *
     * @param  \Guanguans\Di\Container  $container A container instance
     * @return mixed|void
     */
    public function register(Container $container);
}
