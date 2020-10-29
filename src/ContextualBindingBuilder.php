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
 * This file is modified from `illuminate/container`.
 *
 * @see https://github.com/illuminate/container
 */
class ContextualBindingBuilder
{
    /**
     * The underlying container instance.
     * 基础容器实例。
     *
     * @var \Guanguans\Di\Container
     */
    protected $container;

    /**
     * The concrete instance.
     * 具体实例。
     *
     * @var string
     */
    protected $concrete;

    /**
     * The abstract target.
     * 抽象目标。
     *
     * @var string
     */
    protected $needs;

    /**
     * Create a new contextual binding builder.
     * 创建一个新的上下文绑定构建器。
     *
     * @param  \Guanguans\Di\Container  $container
     * @param  string  $concrete
     * @return void
     */
    public function __construct(Container $container, $concrete)
    {
        $this->concrete = $concrete;
        $this->container = $container;
    }

    /**
     * Define the abstract target that depends on the context.
     * 定义依赖于上下文的抽象目标。
     *
     * @param  string  $abstract
     * @return $this
     */
    public function needs($abstract)
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     * 定义上下文绑定的实现。
     *
     * @param  \Closure|string  $implementation
     * @return void
     */
    public function give($implementation)
    {
        $this->container->addContextualBinding(
            $this->concrete,
            $this->needs,
            $implementation
        );
    }
}
