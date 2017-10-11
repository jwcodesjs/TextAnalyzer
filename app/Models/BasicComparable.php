<?php
/**
 * Created by PhpStorm.
 * User: Jonathan
 * Date: 3/3/17
 * Time: 6:07 PM
 */

namespace TextAnalyzer\Models;

use TextAnalyzer\Interfaces\ComparableInterface;

class BasicComparable implements ComparableInterface
{

    /**
     * @var bool
     */
    private $isPositive;
    /**
     * @var
     */
    private $fileName;

    public function __construct($isPositive = false, $fileName)
    {

        $this->isPositive = $isPositive;
        $this->fileName = $fileName;
    }

    public function isPositive(): bool
    {
        return $this->isPositive;
    }

    public function getLemmatizedFileName(): string
    {
        return $this->fileName;
    }

    public function __toString(): string
    {
        return $this->fileName;
    }
}