<?php

use function Vmeretail\PestPluginBdd\example;

describe('this is a feature', function () {

    $string = __FUNCTION__;
    //dd($string);

    beforeEach(function () {

        // Load in the feature file, then check for all scenarios that should be includes
        // Scenarios == 'it' i.e. tests
        // Functions within its are the steps

        //$currentTestFileName = basename(__FILE__);
        //$this->checkFileMatchesAFeature($currentTestFileName);

        //$currentTestFileName = basename();
        //$this->checkFileMatchesAFeature(__FILE__);


    });

    it('Another scenario', function () {

        //$cleanedName = $this->checkTestIsAScenario($this->name());

        function step_I_am_in_a_directory()
        {
            //test()->checkFunctionIsAStep(test()->name(), __FUNCTION__);
        }

        step_I_am_in_a_directory();

        function step_I_should_be_fine()
        {
            //test()->checkFunctionIsAStep(test()->name(), __FUNCTION__);
        }

        step_I_should_be_fine();

    });

    it('Listing two files in a directory and more', function () {

        function step_when_i_do_this()
        {
            // nothing
        }

        step_when_i_do_this();

        function step_and_I_do_that()
        {

        }

        step_and_I_do_that();

    });

    it('has home')->todo();

});




