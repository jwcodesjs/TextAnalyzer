<?php

namespace TextAnalyzer\Commands;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\VarDumper;
use TextAnalyzer\Services\LemmatizeManager;

class LemmatizeCommand extends Command
{

    public $stopWords = array("a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", "along", "already", "also", "although", "always", "am", "among", "amongst", "amoungst", "amount", "an", "and", "another", "any", "anyhow", "anyone", "anything", "anyway", "anywhere", "are", "around", "as", "at", "back", "be", "became", "because", "become", "becomes", "becoming", "been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom", "but", "by", "call", "can", "cannot", "cant", "co", "con", "could", "couldnt", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", "each", "eg", "eight", "either", "eleven", "else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", "everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", "four", "from", "front", "full", "further", "get", "give", "go", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "ie", "if", "in", "inc", "indeed", "interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", "meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", "never", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "not", "nothing", "now", "nowhere", "of", "off", "often", "on", "once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own", "part", "per", "perhaps", "please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", "since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", "system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", "therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "very", "via", "was", "we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", "would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:lemmatize')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Path to directory to read files from')
            ->addOption('output-directory', 'e', InputOption::VALUE_OPTIONAL, 'Path to output directory', false)
            ->addOption('original-suffix', 'o', InputOption::VALUE_OPTIONAL, 'Suffix for original text body file name', 'original')
            ->addOption('raw-suffix', 'r', InputOption::VALUE_OPTIONAL, 'Suffix for raw NLP output', 'raw')
            ->addOption('lemmatized-suffix', 'l', InputOption::VALUE_OPTIONAL, 'Suffix for formatted lemmatized file name', 'lemmatized')
            ->addOption('nest-to-folder', 'n', InputOption::VALUE_OPTIONAL, 'Option to nest all files into individual folders', true)
            ->addOption('server-path', 's', InputOption::VALUE_OPTIONAL, 'Path to NLP server (include port)', 'http://localhost:3456')
            ->addOption('nlp-annotations', 'a', InputOption::VALUE_OPTIONAL, 'List of nlp annotations (eg. tokenize,ssplit,pos,lemma)', 'tokenize,ssplit,pos,lemma')
            ->addOption('write-raw', 'a', InputOption::VALUE_OPTIONAL, 'Write raw nlp output', false)
            ->addOption('remove-punctuation', 'p', InputOption::VALUE_OPTIONAL, 'Option to remove punctuation', true)
            ->addOption('combine-output', 'c', InputOption::VALUE_OPTIONAL, 'Combines lemmatized and original word to same file', false)
            ->addOption('remove-stop-words', 'w', InputOption::VALUE_OPTIONAL, 'Option to remove stop words', false)
            ->setDescription('Lemmatize text');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $options = $input->getOptions();

        $specifiedOptions = array(
            LemmatizeManager::WRITE_TO_DIRECTORY => $options['output-directory'],
            LemmatizeManager::ORIGINAL => $options['original-suffix'],
            LemmatizeManager::RAW => $options['raw-suffix'],
            LemmatizeManager::LEMMATIZED => $options['lemmatized-suffix'],
            LemmatizeManager::NEST_OUTPUT => $options['nest-to-folder'],
            LemmatizeManager::SERVER_PATH => $options['server-path'],
            LemmatizeManager::NLP_ANNOTATORS => $options['nlp-annotations'],
            LemmatizeManager::WRITE_RAW => $options['write-raw'],
            LemmatizeManager::REMOVE_PUNCTUATION => $options['remove-punctuation'],
            LemmatizeManager::COMBINE_OUTPUT => $options['combine-output'],
            LemmatizeManager::REMOVE_STOPS => $options['remove-stop-words'],
            LemmatizeManager::STOP_WORDS => $this->stopWords
        );

        $nlp = new LemmatizeManager($specifiedOptions);
        $scanned_directory = array_diff(scandir($options['directory']), array('..', '.'));
        $count = 0;
        $size = count($scanned_directory);

        foreach ($scanned_directory as $filePath) {
            VarDumper::dump(sprintf('Lemmatizing (%s of %s): %s', ++$count, $size, basename($filePath)));
            $nlp->lemmatizeFile($filePath);
        }

    }


}
