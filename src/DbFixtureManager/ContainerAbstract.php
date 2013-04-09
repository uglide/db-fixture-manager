<?php
/**
 * Created by Igor Malinovskiy <u.glide@gmail.com>.
 * Abstract.php
 * Date: 09.04.13
 */

namespace uglide\DbFixtureManager;

abstract class ContainerAbstract
{
    /**
     * @var
     */
    protected $db;

    /**
     * @var array
     */
    public static $fixtures = array();

    /**
     * @param $dbAdapter
     */
    public function __construct($dbAdapter)
    {
        $this->db = $dbAdapter;
    }

    /**
     * @param $name
     *
     * @return mixed
     * @throws Exception
     */
    public function getFixture($name)
    {
        $class = new \ReflectionClass($this);
        $fixturesProp = $class->getProperty('fixtures');
        $fixtures = $fixturesProp->getValue($this);

        if (!array_key_exists($name, $fixtures)) {
            throw new Exception("Fixture {$name} not founded");
        }

        return $fixtures[$name];
    }

    /**
     * @var array
     */
    private $registeredCleaners = array();

    /**
     * @param $cleanerName
     */
    protected function registerCleaner($cleanerName)
    {
        $this->registeredCleaners[] = $cleanerName;
        $this->registeredCleaners = array_unique($this->registeredCleaners);
    }

    /**
     * @param array $config
     * @param       $varName
     * @param null  $default
     *
     * @return null
     */
    protected function loadVarFromConfig(array $config, $varName, $default = null)
    {
        if (!array_key_exists($varName, $config)) {
            return $default;
        }

        return $config[$varName];
    }

    /**
     * Clean added fixtures from db
     */
    public function clean()
    {
        if (empty($this->registeredCleaners)) {
            return;
        }

        try {
            foreach ($this->registeredCleaners as $cleaner) {
                $this->$cleaner();
            }
        } catch (Exception $e) {
            throw new \Exception(
                "Error occurred on cleaning fixtures : " .  $e->getMessage()
            );
        }
    }
}
