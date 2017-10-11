<?php
/**
 * Created by PhpStorm.
 * User: Jonathan
 * Date: 3/3/17
 * Time: 5:22 PM
 */

namespace AppBundle\Entity;


use TextAnalyzer\Interfaces\ComparableInterface;

class TextAnalysis
{
    const SORT_BY_LOG_ABSOLUTE_VALUE = 0;
    const SORT_ALPHABETICAL = 1;
    const SORT_BY_LOG = 2;
    const SORT_BY_POS_FREQUENCY = 3;
    const SORT_BY_NEG_FREQUENCY = 4;

    private $word;
    private $posFrequency;
    private $negFrequency;

    public function __construct(string $word, int $posFrequency = 0, int $negFrequency = 0)
    {
        $this->word = $word;
        $this->posFrequency = $posFrequency;
        $this->negFrequency = $negFrequency;
    }

    /**
     * @return int
     */
    public function getPosFrequency(): int
    {
        return $this->posFrequency;
    }

    /**
     * @return int
     */
    public function getNegFrequency(): int
    {
        return $this->negFrequency;
    }

    public function leansPositive(): bool
    {
        return $this->getLogComparison() > 0;
    }

    public function getLogComparison(): int
    {
        $posLog = $this->posFrequency == 0 ? 0 : log10($this->posFrequency);
        $negLog = $this->negFrequency == 0 ? 0 : log10($this->negFrequency);

        return $posLog - $negLog;
    }

    public function increaseFrequency(ComparableInterface $object)
    {
        if ($object->isPositive()) {
            $this->incrementPositiveCount();
        } else {
            $this->incrementNegativeCount();
        }
    }

    public function incrementPositiveCount(): int
    {
        $this->posFrequency++;

        return $this->posFrequency;
    }

    public function incrementNegativeCount(): int
    {
        $this->negFrequency++;

        return $this->negFrequency;

    }

    public function compareWith(TextAnalysis $textAnalysis, $sortMethod = self::SORT_ALPHABETICAL)
    {
        switch ($sortMethod) {
            case self::SORT_ALPHABETICAL:
                return strcmp($this->word, $textAnalysis->getWord());
            case self::SORT_BY_LOG:
                return self::compareIntegers($this->getLogComparison(), $textAnalysis->getLogComparison());
            case self::SORT_BY_LOG_ABSOLUTE_VALUE:
                return self::compareIntegers($this->getAbsoluteValueLogComparison(), $textAnalysis->getAbsoluteValueLogComparison());
            case self::SORT_BY_POS_FREQUENCY:
                return self::compareIntegers($this->getPosFrequency(), $textAnalysis->getPosFrequency());
            case self::SORT_BY_NEG_FREQUENCY:
                return self::compareIntegers($this->getNegFrequency(), $textAnalysis->getNegFrequency());
            default:
                throw new \Exception('Invalid sort method: ' . $sortMethod);
        }
    }

    /**
     * @return string
     */
    public function getWord(): string
    {
        return $this->word;
    }

    /**
     * Sorts integers in descending order
     *
     * @param $a
     * @param $b
     * @return int
     */
    private static function compareIntegers($a, $b)
    {
        if ($a == $b) return 0;
        return $a < $b ? 1 : -1;
    }

    public function getAbsoluteValueLogComparison(): int
    {
        $posLog = $this->posFrequency == 0 ? 0 : log10($this->posFrequency);
        $negLog = $this->negFrequency == 0 ? 0 : log10($this->negFrequency);

        return abs($posLog - $negLog);
    }


}