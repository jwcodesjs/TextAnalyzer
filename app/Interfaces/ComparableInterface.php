<?php

namespace TextAnalyzer\Interfaces;
/**
 * Created by PhpStorm.
 * User: Jonathan
 * Date: 3/3/17
 * Time: 3:40 PM
 */
interface ComparableInterface
{
    public function isPositive(): bool;

    public function getLemmatizedFileName(): string;

    public function __toString() : string;
}