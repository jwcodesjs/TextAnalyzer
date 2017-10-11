<?php
/**
 * Created by PhpStorm.
 * User: Jonathan Witchard
 * Date: 3/2/17
 * Time: 3:36 PM
 */

namespace TextAnalyzer\Services;

use AppBundle\Entity\TextAnalysis;
use Symfony\Component\VarDumper\VarDumper;
use TextAnalyzer\Interfaces\ComparableInterface;
use TextAnalyzer\Models\BasicComparable;

class TextAnalysisManager
{
    const AUTO_SKEW = 0;
    const SKEW_BY = 1;
    const SKEW_BY_WORD_COUNT = 2;
    const SKEW_BY_DOCUMENT_COUNT = 3;
    const ALL_POSITIVE = 4;
    
    /**
     * array(word:string => TextAnalysis)
     *
     * @var TextAnalysis[]
     */
    private $textCollection = array();

    /**
     * @var string
     */
    private $directoryPath;

    /**
     * @var string
     */
    private $fileOutputName;

    /**
     * @var int
     */
    private $sortOption = TextAnalysis::SORT_ALPHABETICAL;

    /**
     * This variable denotes what gram text analysis you would like to perform
     *
     * @var integer
     */
    private $gram;

    /**
     * @var array
     */
    private $options;

    public function __construct()
    {
        $this->setOptions();
    }

    /**
     * @param ComparableInterface[] $corpusCollection
     * @param string $directoryPath
     * @param int $gram
     * @param int $sortOption
     * @param string $fileOutputName
     * @return string
     * @throws \Exception
     */
    public function compareCollection(array $corpusCollection, $directoryPath = '', $gram = 1, $sortOption = TextAnalysis::SORT_ALPHABETICAL, $fileOutputName = 'corpus_comparison.txt')
    {
        $this->directoryPath = $directoryPath;
        $this->fileOutputName = $fileOutputName;
        $this->sortOption = $sortOption;
        $this->gram = $gram;

        foreach ($corpusCollection as $object) {

            if (false == $object instanceof ComparableInterface) {
                throw new \InvalidArgumentException('All objects must implement ' . ComparableInterface::class);
            }

            $this->writeToCollection($object);
        }

        return $this->finishAnalysis();
    }

    /**
     * Writes an object into the internal collection
     *
     * @param ComparableInterface $object
     * @throws \Exception
     */
    private function writeToCollection(ComparableInterface $object)
    {
        $contents = $this->getFileContents($object);

        foreach ($contents as $i => $line) {
            $word = self::getNthGram($i, $contents, $this->gram);
            $this->addToCollection($object, $word);
        }
    }

    /**
     * Gets file contents and return an array of each new line
     *
     * @param ComparableInterface $object
     * @return array
     * @throws \Exception
     */
    private function getFileContents(ComparableInterface $object)
    {
        $fileName = $object->getLemmatizedFileName();

        if (false === file_exists($filePath = $this->directoryPath . '/' . $fileName)) {
            throw new \Exception(sprintf('File does not exist for: [%s] at path: [%s]', $object->__toString(), $filePath));
        }

        if (false === ($contents = file($filePath, FILE_IGNORE_NEW_LINES))) {
            throw new \Exception('Failed to get file contents at:  ' . $filePath);
        }

        return $contents;
    }

    /**
     * Concatenates as many string as requested by the gram option
     *
     * @param int $index
     * @param array $wordList
     * @param int $gram
     * @return string
     * @throws \Exception
     */
    private static function getNthGram($index, $wordList, $gram = 1)
    {
        $i = 1;
        self::parseLineForWord($index, $wordList, $mergedWord);

        while ($i < $gram) {
            if (array_key_exists($index + $i, $wordList)) {
                self::parseLineForWord($index + $i, $wordList, $word);
                $mergedWord .= '_' . $word;
            } else {
                break;
            }
            $i++;
        }

        return $mergedWord;
    }

    /**
     * Based on the system format parses a line
     * only to extract and set the desired word
     *
     * @param $i
     * @param $wordList
     * @param $word
     * @throws \Exception
     */
    private static function parseLineForWord($i, $wordList, &$word)
    {
        if (($count = sizeof($params = explode("\t", $wordList[$i]))) == 4) {
            list(, , , $word) = explode("\t", $wordList[$i]);
        } elseif ($count == 3) {
            list(, , $word) = explode("\t", $wordList[$i]);
        } else {
            VarDumper::dump($params);
            Throw New \Exception(sprintf("Parsed file line contains %s fields: [%s] (allowed 3 or 4)", $count, $wordList[$i]));
        }
    }

    /**
     *
     *
     * @param ComparableInterface $object
     * @param                     $word
     */
    private function addToCollection(ComparableInterface $object, $word)
    {
        //initial add
        if (false == array_key_exists($word, $this->textCollection)) {
            $this->textCollection[$word] = new TextAnalysis($word);
        }

        $this->textCollection[$word]->increaseFrequency($object);
    }

    /**
     * Completes the class text analysis
     *
     * @return string
     * @throws \Exception
     */
    private function finishAnalysis()
    {
        //Skew collection if so desired
        if ($this->options[self::AUTO_SKEW]) {
        }


        $this->sortCollection();

        $path = $this->writeCollectionsToFile();

        $this->resetClass();

        return $path;
    }

    /**
     * Sorts the internal collection
     */
    private function sortCollection()
    {
        $sortOption = $this->sortOption;
        switch ($sortOption) {
            case TextAnalysis::SORT_ALPHABETICAL:
                ksort($this->textCollection);
                break;
            default:
                uasort($this->textCollection, function ($ta1, $ta2) use ($sortOption) {
                    /** @var TextAnalysis $ta1 , $ta2 */
                    return $ta1->compareWith($ta2, $sortOption);
                });
                break;
        }
    }

    /**
     * Writes text analysis to file
     *
     * @return string
     * @throws \Exception
     */
    private function writeCollectionsToFile()
    {
        $fileName = $this->directoryPath . '/../' . $this->fileOutputName;

        if (false == ($fp = fopen($fileName, 'w'))) {
            throw new \Exception('Failed to write collection to path: ' . $fileName);
        }

        fwrite($fp, $this->getHeader());

        foreach ($this->textCollection as $word => $ta) {

            $writeLine = $this->getRowText($ta);

            fwrite($fp, $writeLine);
        }

        fclose($fp);

        return $fileName;
    }

    private function getHeader()
    {
        if ($this->options[self::ALL_POSITIVE]) {
            return sprintf("%s\t\t%s\n", 'Frequency', 'Word');
        }

        $format = "%s\t\t%s\t\t%s\t%s\t\t%s\n";

        return sprintf($format, "PosFreq", "NegFreq", "Abs. Log", "Reg Log", 'Word');
    }

    private function resetClass()
    {
        $this->textCollection = array();
    }

    private function getRowText(TextAnalysis $textAnalysis)
    {
        if ($this->options[self::ALL_POSITIVE]) {
            return sprintf("%s\t\t%s\n", $textAnalysis->getPosFrequency(), $textAnalysis->getWord());
        }

        return sprintf("%s\t\t\t%s\t\t\t%s\t\t\t%s\t\t\t%s\n",
            $textAnalysis->getPosFrequency(),
            $textAnalysis->getNegFrequency(),
            $textAnalysis->getAbsoluteValueLogComparison(),
            $textAnalysis->getLogComparison(),
            $textAnalysis->getWord());
    }

    /**
     * Compares an entire directory of files given the directory path and
     * a collection of positive articles
     *
     * @param       $directoryPath
     * @param $gram
     * @param array $positiveArticles
     * @param string $fileOutputName
     * @param int $sortOption
     * @param bool $autoSkew
     * @return string File output path
     * @throws \Exception
     */
    public function compareDirectory($directoryPath, $gram, $positiveArticles = null, $fileOutputName = 'corpus_comparison.txt', $sortOption = TextAnalysis::SORT_ALPHABETICAL, $autoSkew = false)
    {
        $this->directoryPath = $directoryPath;
        $this->sortOption = $sortOption;
        $this->fileOutputName = $fileOutputName;
        $this->gram = $gram;

        $fileNames = $this->getFileNames();
        $comparable = null;
        $allPositive = is_null($positiveArticles) || $this->options[self::ALL_POSITIVE];
        foreach ($fileNames as $fileName) {
            $isPositive = $allPositive || in_array(str_replace('.txt', '', $fileName), $positiveArticles);
            $comparable = new BasicComparable($isPositive, $fileName);
            $this->writeToCollection($comparable);
        }

        return $this->finishAnalysis();
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getFileNames()
    {
        if (false == is_dir($this->directoryPath)) {
            throw new \Exception('Invalid directory at path: ' . $this->directoryPath);
        }

        $files = glob($this->directoryPath . '/*.txt');

        $files = array_map('basename', $files);

        return $files;
    }

    /**
     * @return TextAnalysis[]
     */
    public function getTextAnalysis()
    {
        return $this->textCollection;
    }

    public function setOptions(array $options = array())
    {
        $defaultOptions = array(
            self::AUTO_SKEW => false,
            self::SKEW_BY => self::SKEW_BY_WORD_COUNT,
            self::ALL_POSITIVE => false
        );

        $this->options = array_replace($defaultOptions, $options);
    }
}