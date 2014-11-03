<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Mocks a builder for use in testing
 */
namespace RDev\Tests\Views\Mocks;
use RDev\Views;

class BarBuilder implements Views\IBuilder
{
    /**
     * {@inheritdoc}
     */
    public function build(Views\ITemplate $template)
    {
        $template->setTag("bar", "baz");

        return $template;
    }
}