<?php
/**
 * Test case
 *
 * @category 
 * @package
 * @subpackage
 *
 * @author Igor Malinovskiy <glide.name>
 * @file TestCase.php
 * @date: 09.04.13
 * @time: 10:21
 */

namespace uglide\DbFixtureManager;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Manager
     */
    protected $fixturesManager;

    /**
     * fixtures put here
     */
    protected $fixture;

    /**
     * @param null   $name
     * @param array  $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->fixturesManager = new Manager($this->getDb());

        /**
         * Register custom autoLoader
         */
        $this->registerFixturesAutoloader();
    }

    /**
     * Meta Fixtures SetUp
     */
    public function setUp()
    {
        parent::setUp();

        $this->fixturesManager->prepareFixtures($this);
    }

    /**
     * Meta Fixtures tearDown
     */
    protected function tearDown()
    {
        $this->fixturesManager->removeFixtures();

        parent::tearDown();
    }

    /**
     * @param $name
     * @param $value
     */
    public function setFixture($name, $value)
    {
        $this->fixture[$name] = $value;
    }

    abstract protected function getDb();

    abstract protected function registerFixturesAutoloader();
}