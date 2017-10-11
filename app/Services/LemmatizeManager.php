<?php
/**
 * Created by PhpStorm.
 * User: Jonathan Witchard
 * Date: 3/2/17
 * Time: 3:36 PM
 */

namespace TextAnalyzer\Services;

class LemmatizeManager
{

    # region User options
    /**
     * The path to the stanford server
     * type. string
     * ex . http://localhost:1234
     */
    const SERVER_PATH = 1;

    /**
     * Defines the annotations to be used on the
     * stanford nlp server. Must be comma delimited, no spaces
     * type. string
     * ex. tokenize,ssplit,pos,lemma
     */
    const NLP_ANNOTATORS = 2;

    /**
     * Option to write the raw response from the NLP server.
     * type. bool
     * ex. true/false
     */
    const WRITE_RAW = 3;

    /**
     * Option to specify the directory to place the output
     * type. string
     * ex. './put/this/here'
     */
    const WRITE_TO_DIRECTORY = 4;

    /**
     * Option to specify the exact file name you wish on all
     * outputted files. Don't include an extension
     * type. string
     * ex. 'name_of_my_file'
     */
    const FILE_NAME = 6;

    /**
     * Option to nest all the results into a folder.
     * The file name can be set with the RAW/ORIGINAL/LEMMATIZED
     * options
     * type. bool
     * ex. false
     */
    const NEST_OUTPUT = 7;

    /**
     * Option to specify a suffix for filenames or if nesting
     * the results a folder name.
     */
    const RAW = 8;
    const ORIGINAL = 9;
    const LEMMATIZED = 10;

    /**
     * Outputs lemmatized and original text in the same document
     */
    const COMBINE_OUTPUT = 11;

    /**
     * Option to remove stop words
     */
    const REMOVE_STOPS = 12;

    /**
     * Option to remove punctuation
     */
    const REMOVE_PUNCTUATION = 13;

    const STOP_WORDS = 14;

    # endregion User options

    //Not user options
    const PRETTY_PRINT = 'prettyPrint';
    const TIMEOUT = 'timeout';
    const DEFAULT_STOP_WORDS = array('a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'to', 'was', 'were', 'will', 'with');

    /**
     * Options
     *
     * @var array
     */
    private $options;

    private $optionsSet = false;

    public function __construct($options = null)
    {
        if (false === extension_loaded('curl')) {
            throw new \Exception('Curl extension must be loaded to use this package');
        }

        $this->setOptions($options);
    }

    public function setOptions(array $options = null)
    {
        $this->options = self::parseOptions($options);
        $this->optionsSet = true;

        return $this;
    }

    private static function parseOptions(array $options = null)
    {

        $defaultOptions = self::getDefaultOptions();

        if (is_array($options) && false == empty($invalidOptions = array_diff_key($options, $defaultOptions))) {
            throw new \Exception('Invalid options: ' . implode(' ', $invalidOptions));
        }

        if (false == is_null($options)) {
            $defaultOptions = array_replace($defaultOptions, $options);
        }

        $defaultOptions[self::PRETTY_PRINT] = true;
        $defaultOptions[self::TIMEOUT] = 50000;

        return $defaultOptions;
    }

    public static function getDefaultOptions()
    {
        return array(
            self::SERVER_PATH => 'http://localhost:3456',
            self::NLP_ANNOTATORS => 'tokenize,ssplit,pos,lemma',
            self::WRITE_RAW => false,
            self::WRITE_TO_DIRECTORY => \AppKernel::getStanfordDir(),
            self::FILE_NAME => 'lemma_export',
            self::RAW => 'raw',
            self::ORIGINAL => 'original',
            self::LEMMATIZED => 'lemmatized',
            self::NEST_OUTPUT => false,
            self::COMBINE_OUTPUT => false,
            self::REMOVE_PUNCTUATION => false,
            self::REMOVE_STOPS => false,
            self::STOP_WORDS => self::DEFAULT_STOP_WORDS,
        );

    }

    public function lemmatizeFile($filePath, array $options = null)
    {
        if (false === file_exists($filePath)) {
            Throw new \Exception('File does not exist at path: ' . $filePath);
        }

        if (false == $contents = file_get_contents($filePath)) {
            Throw new \Exception('Failed to retrieve file contents: ' . $filePath);
        }

        return $this->lemmatizeText($contents, $filePath, $options);
    }

    public function lemmatizeText($text, $fileName, array $options = null)
    {
        //Clean text
        $text = str_replace('"', "", $text);
        $text = str_replace("'", "", $text);

        if (false === $this->optionsSet) {
            $this->options = self::parseOptions($options);
        }

        $this->options[self::FILE_NAME] = $fileName;

        $paths = $this->getFromServer($text);

        return $paths;
    }

    private function getFromServer($text)
    {
        $serverPath = $this->options[self::SERVER_PATH];

        if (is_string($serverPath) && !$fp = curl_init($serverPath)) {
            throw new \Exception('Must define valid server path');
        }

        // create a shell command
        $command = sprintf('curl --data "%s" "%s"', $text, $this->buildUrl());

        try {
            // do the shell command
            $response = shell_exec($command);

        } catch (Exception $e) {
            Throw new \Exception('Caught exception: ', $e->getMessage(), "\n");
        }

        return $this->parseCurlResponse($response);
    }

    private function buildUrl()
    {
        $urlOptions = '';
        $severOptions = $this->getServerOptions();
        foreach ($severOptions as $key => $option) {

            if (is_array($option)) {
                $option = implode(',', $option);
            }

            if (is_bool($option)) {
                $option = $option === true ? 'true' : 'false';
            }
            $urlOptions .= sprintf(',"%s":"%s"', $key, $option);
        }

        $urlOptions = ltrim($urlOptions, ',');
        $base = $this->options[self::SERVER_PATH] . sprintf('?properties={%s}', urlencode($urlOptions));

        return $base;
    }

    private function getServerOptions()
    {
        return array(
            'annotators' => $this->options[self::NLP_ANNOTATORS],
            self::PRETTY_PRINT => $this->options[self::PRETTY_PRINT],
            self::TIMEOUT => $this->options[self::TIMEOUT]
        );
    }

    private function parseCurlResponse($rawResponse)
    {
        $response = json_decode($rawResponse, true);

        $lemmas = '';
        $originals = '';

        $format = $this->options[self::COMBINE_OUTPUT]
            ? "%s\t%s\t%s\t%s\n"
            : "%s\t%s\t%s\n";

        foreach ($response['sentences'] as $i => $sentence) {
            $index = $sentence['index'];
            foreach ($sentence['tokens'] as $token) {

                if ($this->options[self::REMOVE_PUNCTUATION] && ctype_punct($token['lemma'])) {
                    continue;
                }

                if ($this->options[self::REMOVE_STOPS] && $this->isStopWord($token['lemma'])) {
                    continue;
                }

                if ($this->options[self::COMBINE_OUTPUT]) {
                    $lemma = sprintf($format, $index, $token['index'], $token['originalText'], $token['lemma']);
                    $lemmas .= $lemma;
                } else {
                    $original = sprintf($format, $index, $token['index'], $token['originalText']);
                    $lemma = sprintf($format, $index, $token['index'], $token['lemma']);
                    $originals .= $original;
                    $lemmas .= $lemma;
                }
            }
        }

        $paths = array();

        if (false == $this->options[self::COMBINE_OUTPUT]) {
            if ($this->options[self::ORIGINAL] !== false) {
                //Write original document
                $paths[$this->options[self::ORIGINAL]] = $this->writeFile($originals, self::ORIGINAL);
            }
        }

        //Always write lemmatized document
        $paths[$this->options[self::LEMMATIZED]] = $this->writeFile($lemmas, self::LEMMATIZED);

        if ($this->options[self::WRITE_RAW]) {
            $paths[$this->options[self::RAW]] = $this->writeFile($rawResponse, self::RAW);
        }

        return $paths;
    }

    private function isStopWord($word)
    {
        return in_array($word, $this->options[self::STOP_WORDS]);
    }

    private function writeFile($text, $type)
    {
        $path = $this->getFilePath($type);
        $fp = fopen($path, 'w');
        fwrite($fp, $text);
        fclose($fp);

        return $path;
    }

    private function getFilePath($type)
    {
        $typeString = $this->options[$type];
        //Check and see if option directory exists
        if (false === is_dir($dir = $this->options[self::WRITE_TO_DIRECTORY])) {
            throw New \Exception('WRITE_TO_DIRECTORY is not a directory: ' . $dir);
        }

        $fileName = $this->options[self::FILE_NAME];
        $ext = $type == self::RAW ? '.json' : '.txt';

        if ($this->options[self::NEST_OUTPUT]) {
            //Make the passed in folder directory
            if (false == is_dir(($newDir = $dir . '/' . $typeString))) {
                if (false == mkdir($newDir)) {
                    throw New \Exception('Failed to make directory: ' . $newDir);
                }

            }
            $path = $newDir . '/' . $fileName . $ext;
        } else {
            $path = $dir . '/' . $fileName . '-' . $typeString . $ext;
        }

        return $path;

    }
}