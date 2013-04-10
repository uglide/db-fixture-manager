<?php
/**
 * Created by Igor Malinovskiy <u.glide@gmail.com>
 * Date: 28.11.12
 * Time: 23:24
 */

namespace uglide\DbFixtureManager;

class Manager
{
    /**
     * Annotation var for determine fixture
     */
    const FIXTURE_ANNOTATION_KEY = 'fixture';

    /**
     * Const's for fixture containers loading
     */
    const FIXTURE_CONTAINER_CLASS_SUFFIX = "_Fixtures_Container";
    const TARGET_TESTCASE_CLASS = 'PHPUnit_Framework_TestCase';
    const MAX_CLASS_NESTING = 5;

    /**
     * Matcher's for parse @fixture annotation
     */
    const MATCHER_TARGET_VAR = '/^\$[a-z]/i';
    const MATCHER_METHOD = '/^([a-z_]+)::([a-z_0-9]+)(\+)*/i';
    const MATCHER_FIXTURE_VAR = '/^([a-z_]+)::(\$[a-z_]+)/i';

    /**
     * @var
     */
    private $dbAdapter;

    /**
     * @var ContainerAbstract[]
     */
    private $containers = array();

    /**
     * @param $dbAdapter
     */
    public function __construct($dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    /**
     * @param TestCase $testCase
     *
     * @throws \Exception
     */
    public function prepareFixtures($testCase)
    {
        if (!$this->isFixturesUsedInTest($testCase)) {
            return;
        }

        $fixtures = $this->getFixturesDataFromAnnotation(
            $testCase
        );

        if (empty($fixtures)) {
            throw new \Exception("Invalid fixtures definition!");
        }

        foreach ($fixtures as $fixture) {

            /** @var $fixtureContainer ContainerAbstract */
            $fixtureContainer = $this->loadFixtureContainer($fixture);

            try {

                if (isset($fixture['method'])) {

                    $this->loadMethodFixture($fixture, $testCase, $fixtureContainer);

                } elseif (isset($fixture['fixtureVar'])) {

                    $this->loadVarFixture($fixture, $testCase, $fixtureContainer);
                }

            } catch (Exception $e) {
                 throw new \Exception(
                     "Error occurred on execution {$fixture['module']}::{$fixture['method']}:"
                     . $e->getMessage()
                 );
            }
        }
    }

    /**
     * @param $fixture
     * @param $testCase TestCase
     * @param $fixtureContainer ContainerAbstract
     */
    protected function loadMethodFixture($fixture, $testCase, $fixtureContainer)
    {
        $method = $fixture['method'];

        if (empty($fixture['passDataFromProvider'])) {

            $result = $fixtureContainer->$method();

        } else {
            $testData = $this->getDataFromTestCase($testCase);

            /**
             * If data for current setUp method specified
             * provide to setUp method only this item of data array
             */
            if (is_array($testData) && array_key_exists($method, $testData)) {
                $testData = $testData[$method];
            }

            $result = $fixtureContainer->$method($testData);
        }

        if (array_key_exists('variable', $fixture)) {
            $testCase->setFixture($fixture['variable'], $result);
        }
    }

    /**
     * @param $fixture
     * @param $testCase TestCase
     * @param $fixtureContainer ContainerAbstract
     */
    protected function loadVarFixture($fixture, $testCase, $fixtureContainer)
    {
        $targetFixtureName = (isset($fixture['variable'])) ?
            $fixture['variable'] : $fixture['fixtureVar'];

        $testCase->setFixture(
            $targetFixtureName,
            $fixtureContainer->getFixture($fixture['fixtureVar'])
        );
    }

    /**
     * @param array $fixture
     *
     * @return ContainerAbstract
     * @throws Exception
     */
    private function loadFixtureContainer(array $fixture)
    {
        $module = $fixture['module'];

        if (array_key_exists($module, $this->containers)) {

            $fixtureContainer = $this->containers[$module];

            if (!($fixtureContainer instanceof ContainerAbstract)) {
                throw new Exception(
                    "Invalid Fixture Container in cache"
                );
            }

        } else {
            try {
                $containerClassName = $this->getClassNameForFixture($fixture);

                $fixtureContainer = new $containerClassName($this->dbAdapter);

                $this->containers[$module] = $fixtureContainer;

            } catch (Exception $e) {
                throw new Exception(
                    "Can't load fixture container for module "
                    . $module . " : " . $e->getMessage()
                );
            }
        }

        return $fixtureContainer;
    }

    /**
     * @param $fixture
     *
     * @return string
     */
    private function getClassNameForFixture($fixture)
    {
        return $fixture['module'] . self::FIXTURE_CONTAINER_CLASS_SUFFIX;
    }

    /**
     * @param TestCase $testCase
     *
     * @return mixed
     * @throws Exception
     */
    private function getDataFromTestCase(TestCase $testCase)
    {
        $testCaseClass = new \ReflectionObject($testCase);

        $targetClassFound = false;
        $parentCount = 0;

        while ($parentCount < self::MAX_CLASS_NESTING) {
            $testCaseClass = $testCaseClass->getParentClass();

            if ($testCaseClass->getName() == self::TARGET_TESTCASE_CLASS) {
                $targetClassFound = true;
                break;
            }
            $parentCount++;
        }

        if (!$targetClassFound) {
            throw new Exception(
                "PHPUnit test case class not found
                    in parents of current test case class"
            );
        }

        try {
            $dataProp = $testCaseClass->getProperty('data');
            $dataProp->setAccessible(true);
            return $dataProp->getValue($testCase);
        } catch (\ReflectionException $ex) {
            throw new Exception(
                "Can't get data by reflection"
            );
        }
    }

    /**
     * @param TestCase $testCase
     *
     * @return array
     */
    private function getFixturesDataFromAnnotation(TestCase $testCase)
    {
        $anotations = $testCase->getAnnotations();

        $fixturesRawData = $anotations['method'][self::FIXTURE_ANNOTATION_KEY];

        $fixtures = array();

        foreach ($fixturesRawData as $fixtureData) {

            $fixture = $this->parseAnnotation($fixtureData);

            if ($this->isValidFixture($fixture)) {
                $fixtures[] = $fixture;
            }
        }

        return $fixtures;
    }

    /**
     * @param $fixtureData
     *
     * @return array
     */
    private function parseAnnotation($fixtureData)
    {
        $fixture = array();
        $fixtureParts = explode(' ', $fixtureData);

        foreach ($fixtureParts as $part) {
            $matches = array();

            switch (true) {
                case preg_match(self::MATCHER_TARGET_VAR, $part, $matches):
                    $fixture['variable'] = trim(str_replace('$', '', $part));
                    break;
                case preg_match(self::MATCHER_METHOD, $part, $matches):
                    $fixture['module'] = $matches[1];
                    $fixture['method'] = $matches[2];
                    $fixture['passDataFromProvider'] = (isset($matches[3])) ? true : false;
                    break;
                case preg_match(self::MATCHER_FIXTURE_VAR, $part, $matches):
                    $fixture['module'] = $matches[1];
                    $fixture['fixtureVar'] = trim(
                        str_replace('$', '', $matches[2])
                    );
                    break;
            }
        }

        return $fixture;
    }

    /**
     * @param array $fixture
     *
     * @return bool
     */
    private function isValidFixture(array $fixture)
    {
        return array_key_exists('module', $fixture)
            && (array_key_exists('method', $fixture)
                || array_key_exists('fixtureVar', $fixture));
    }

    /**
     * @param TestCase $testCase
     *
     * @return bool
     */
    private function isFixturesUsedInTest(TestCase $testCase)
    {
        $anotations = $testCase->getAnnotations();

        return array_key_exists(
            self::FIXTURE_ANNOTATION_KEY,
            $anotations['method']
        );
    }

    /**
     * @throws Exception
     */
    public function removeFixtures()
    {
        foreach ($this->containers as $module => $container) {
            try {
                $container->clean();
            } catch (Exception $e) {
                throw new Exception(
                    "Error occurred on cleaning fixtures
                        installed from {$module} fixtures container"
                );
            }
        }
    }
}
