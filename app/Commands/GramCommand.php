<?php

namespace TextAnalyzer\Commands;

use AppBundle\Entity\TextAnalysis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\VarDumper;
use TextAnalyzer\Services\TextAnalysisManager;

define('FREQUENCY_CUTOFF', 0.4);
define('SUMMARY_FILE_NAME', 'text_analysis_summary.csv');

class GramCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:analyze')
            ->addOption('directory', null, InputOption::VALUE_REQUIRED, 'Runs analysis on entire given directory')
            ->addOption('filename', null, InputOption::VALUE_OPTIONAL, 'Output file name', 'corpus_collection.txt')
            ->addOption('gram', null, InputOption::VALUE_OPTIONAL, 'Specifies the word gram to calculate', 1)
            ->addOption('rm-punctuation', null, InputOption::VALUE_OPTIONAL, 'Specifies the word gram to calculate', true)
            ->addOption('rm-stops', null, InputOption::VALUE_OPTIONAL, 'Specifies the word gram to calculate', true)
            ->addOption('to-gram', null, InputOption::VALUE_REQUIRED, 'Specifies the word gram to calculate', -1)
            ->setDescription('Analyses article lemmatized text');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if ($directory = $input->getOption('directory')) {

            if(is_dir($directory)) {
                VarDumper::dump('Directory not found');
                return;
            }

            $fileName = $input->getOption('filename');

            $am = new TextAnalysisManager();

            $am->setOptions(array(TextAnalysisManager::ALL_POSITIVE => true));

            if (($gramCount = intval($input->getOption('to-gram'))) >= 0) {

                $paths = array();
                $currentGram = $input->getOption('gram');
                $fileName = str_replace('.txt', '', $fileName);

                do {
                    VarDumper::dump('Performing  ' . $currentGram . ' gram');

                    $paths[] = $am->compareDirectory($directory, $currentGram, null, $fileName . "_g$currentGram.txt", TextAnalysis::SORT_BY_POS_FREQUENCY);
                    $currentGram++;
                } while ($currentGram <= $gramCount);

                $dump = $paths;
            } else {
                $dump = $am->compareDirectory($directory, $input->getOption('gram'), null, $fileName, TextAnalysis::SORT_BY_POS_FREQUENCY);
            }
        }

        if (isset($dump)) {
            VarDumper::dump($dump);
        }

    }
}
