<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Keywords\ArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Parser;

class GherkinParser
{
    public function gherkin($filename) : FeatureNode
    {
        $keywords = new ArrayKeywords(array(
            'en' => array(
                'feature'          => 'Feature',
                'background'       => 'Background',
                'scenario'         => 'Scenario',
                'scenario_outline' => 'Scenario Outline|Scenario Template',
                'examples'         => 'Examples|Scenarios',
                'given'            => 'Given',
                'when'             => 'When',
                'then'             => 'Then',
                'and'              => 'And',
                'but'              => 'But'
            )
        ));
        $lexer  = new Lexer($keywords);
        $parser = new Parser($lexer);

        return $parser->parse($filename);
    }

    public function featureName(string $featureFileContents)
    {
        $featureObject = $this->gherkin($featureFileContents);
        ray($featureObject);
        return $featureObject->getTitle();
    }
}
