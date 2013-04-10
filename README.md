DbFixtureManager 
================


DbFixtureManager- создан с целью упростить управление фикстурами БД и подготовку тестового окружения для выполнения тестов.

## Проблематика ##

Для лучшего понимания происходящего, рассмотрим следующий тривиальный пример:


Предположим, что в нашей системе разработан некий модуль shops, функционал которого покрыт различными тестами (модульными и функциональными):
![](http://glide.name/docs/zfc-transition/mfm/1.PNG)


Допустим, что наша текущая задача - **добавить тест для этого модуля**, который нуждается в фикстуре магазина добавленной в БД. Смотря на файловую структуру тестов для shops модуля, мы можем сделать предположение, что фикстура магазина и код, который добавляет ее в БД уже есть в каком-то из тестов.

>     Проблема № 1 - Необходимо просмотреть все тесты данного модуля, для того, чтобы определить есть ли необходимый код для подготовки тестового окружения или нет.

>     Проблема № 2 - Проблема №1 влечет за собой дублирование кода в тестах, что существенно увеличивает их хрупкость и делает более сложным добавление новых тестов.

После титанических усилий мы все таки находим некий код, который добавляет фикстуру магазина, например:

`
public static function setUpShop()
{
  $shop = $shops->createRow(self::$fixtures['shop']);
  $shop->save();
  return $shop;
}
`

Мы добавляем его вызов в тест, и должны не забыть удалить фикстуру из БД после окончания теста

>     Проблема № 3 - Необходимость "ручного" контроля очистки фикстур

Но в ходе написания тестов очень часто возникает необходимость подготовить несколько связанных сущностей в БД и тут возникает следующая проблема:

>     Проблема № 4 - Неявные зависимости между тест кейсами за счет перекрестного использования методов для подготовки БД


## Решение ##

**Использование DbFixtureManager :)**

Для начала необходимо создать класс - контейнер для фикстур:

![](http://glide.name/docs/zfc-transition/mfm/2.PNG)

Класс наследуется от uglide\DbFixtureManager\ContainerAbstract:
```
use uglide\DbFixtureManager\ContainerAbstract;
class Shops_Fixtures_Container extends ContainerAbstract
{
    ...
}
```

В данном классе необходимо определить методы для подготовки окружения, в данном случае это будет ***setUpShop()***


```
  use uglide\DbFixtureManager\ContainerAbstract;
  class Shops_Fixtures_Container extends ContainerAbstract
	{

   	 public static $fixtures = array(
	        'shop' =array(
	            'id' =1,
	            'alias' ='testshop',
	            'name' ='Test Shop',
	            'country_id'    = 1,
	            'state_id'  = 25,
	            'city_id'   = 28
	        )
	    );
	
	    /**
	     * @param array $config
	     *
	     * @return array
	     */
	
	    public function setUpShop($config = array('resetTable' =true))
	    {
	 
	        $shops = new Shops_Model_Shop_Table();
	        $shops->getDefaultAdapter()->query('SET FOREIGN_KEY_CHECKS=0;');
	        if (isset($config['resetTable']) && $config['resetTable']) {
	            $shops->delete(' true = true ');
	        }
	 
	        $shop = $shops->createRow(self::$fixtures['shop']);
	        $shop->save();
	 
	        /**
	         * Register cleaner
	         */
	        $this->_registerCleaner('_tearDownShop');
	        return $fixture;
	    }
	 
	    protected function _tearDownShop()
	    {
	        $shops = new Shops_Model_Shop_Table();
	        $shops->getDefaultAdapter()->query('SET FOREIGN_KEY_CHECKS=0;');
	        $shops->delete(' true = true ');
	    }
}
```

**!** Метод, который поднимает фикстуру **обязательно должен регистрировать сборщик мусора (cleaner)**.  Cleaner - это метод, который будет вызван автоматически для удаления поднятой фикстуры.

Также необходимо определить загрузчик контейнеров фикстур, например его можно определить в bootstrap.php:

```
/**
 * Init Fixtures loader
 */
spl_autoload_register(function($className) {
        $matches = array();

        if (preg_match('/([a-z]+)_Fixtures_Container/i', $className, $matches)) {
            $path = __DIR__ . '/fixtures/' . $matches[1] . '.php';

            if (file_exists($path)) {
                require_once $path;
            }
        }
    });

```

Далее необходимо унаследовать абстрактный класс тест кейса, и реализовать метод получения объекта для работы с Бд

```
use uglide\DbFixtureManager\TestCase;

class BaseTestCase extends TestCase
{

    protected function getDb()
    {
        return Database::instance();
    }

}
```

Все тест-кейсы с фикстурами будут наследоваться от этого класса


Как использовать в тестах:

```

// Example 1 

    /**
     * Самый простой случай - нам необходимо добавить в БД фикстуру,
     * которая не используется после этого в тесте
     * @fixture Shops::setUpShop
     */
    public function testIndexAction()
    {
        // код теста
    }


// Example 2 

    /**
     * Добавляем в БД фикстуру,
     * и результат выполнения метода setUpShop будем использовать в тесте
     * @fixture $shop Shops::setUpShop
     */
    public function testIndexAction()
    {
        // ....
        $this->_fixture['shop']; // фикстура доступна по указанному имени в массиве $this->_fixtures
        // ....
    }

// Example 3 

    /**
     * Добавляем в БД фикстуру, с помощью метода setUpShop,
     * которому нужно передать определенные параметры
     * знак "+" после названия метода обозначает,
     * что в данный метод будет передана фикстура
     * из дата-провайдера в качестве аргумента
     * @fixture $shop Shops::setUpShop+
     * @dataProvider shopDataProvider
     */
    public function testIndexAction($fixtureSettings) // фикстура передаваемая дата-провайдером доступна как аргумент теста
    {
        // ....
        $this->_fixture['shop']; // фикстура доступна по указанному имени в массиве $this->_fixtures
        // ....
    }
     
    /**
    * Стандартный дата-провайдер PHPUnit
    */
    public function shopDataProvider()
    {
        return array('validTest' => array('resetTable' => false));
    }
     
// Example 4 


    /**
     * Добавляем в БД несколько фикстур,
     * @fixture $shop Shops::setUpShop
     * @fixture $user Users::setUpUser
     */
    public function testIndexAction()
    {
        // ....
        $this->_fixture['shop'];
        $this->_fixture['user'];
        // ....
    }    

//Example 5 

    /**
     * Добавляем в БД несколько фикстур,
     * с помощью нескольких методов.
     * Обратите внимание на то, что методы
     * получают различные данные из дата провайдера
     * @fixture $shop Shops::setUpShop
     * @fixture $user Users::setUpUser
     * @dataProvider shopDataProvider
     */
    public function testIndexAction()
    {
        // ....
        $this->_fixture['shop'];
        $this->_fixture['user'];
        // ....
    }    
 
    /**
    * Стандартный дата-провайдер PHPUnit
    */
    public function shopDataProvider()
    {
        return array(
            'validTest' =>
                array(
                    'setUpShop' => array('resetTable' => false), //эти данные будут переданы в Shops::setUpShop
                    'setUpUser' => array('id' => 100), // а эти в Users::setUpUser
                )
        );
    }
```
Использование фикстур из контейнера

Как вы уже догадываетесь из заголовка, в данном менеджере фикстур есть возможность использовать фикстуры непосредственно из контейнера.

Предположим, у нас есть следующий контейнер с фикстурами:

```
        use uglide\DbFixtureManager\ContainerAbstract;
        class Shops_Fixtures_Container extends ContainerAbstract
	{

	    /**
	     * Фикстуры должны быть добавлены в статическую
	     * переменную класса $fixtures
	     */

	     public static $fixtures = array(
	        'shop' => array(
	            'id' => 1,
	            'alias' => 'testshop',
	            'name' => 'Test Shop',
	            'country_id'    =>  1,
	            'state_id'  =>  25,
	            'city_id'   =>  28
	        )
	    );
	 }

```

Данную фикстуру мы можем использовать в тестах следующим образом:

```

	//  Example 1
    /**
     * Указываем имя переменной,
     * в которую будет записана фикстура
     * и имя фикстуры со знаком $
     * @fixture $superShop Shops::$shop 
     */
    public function testIndexAction()
    {
        // ....
        // теперь мы можем использовать в тесте
        // фикстуру
        $this->_fixture['superShop'];
        // ....
    } 
 
	//  Example 2 
    /**
     * Если имя целевой переменной не указано явно,
     * то фикстура будет доступна в массиве
     * $this->_fixture по своему индексу
     * из контейнера
     * @fixture Shops::$shop    
     */
    public function testIndexAction()
    {
        // ....
        // теперь мы можем использовать в тесте
        // фикстуру
        $this->_fixture['shop'];
        // ....
    }

```

**Но зачем это, если можно просто вызвать в тесте статическую переменную контейнера?!**

Работая с фикстурами через менеджер у вас **есть возможность получать динамически созданные фикстуры**:

```
        use uglide\DbFixtureManager\ContainerAbstract;
        class Shops_Fixtures_Container extends ContainerAbstract
	{
	    /**
	     * Статическая фикстура
	     */
	     public static $fixtures = array(
	        'shop' => array(
	            'id' => 1,
	            'alias' => 'testshop',
	            'name' => 'Test Shop',
	            'country_id'    =>  1,
	            'state_id'  =>  25,
	            'city_id'   =>  28
	        )
	    );
	 
	    public function __construct(Zend_Db_Adapter_Abstract $dbAdapter)
	    {
	        parent::__construct($dbAdapter);
	 
	        // генерируем фикстуру 
	        self::$fixtures['someGeneratedFixture'] = $this->someShopFixtureGenerator();
	    }
	 
	    // .......
	 }
	
	/**
	     * Мы можем получить динамически созданную
	     * фикстуру в тесте
	     * @fixture $someGeneratedFixture Shops::$someGeneratedFixture
	     */
	    public function testIndexAction()
	    {
	        // ....
	        $this->_fixture['someGeneratedFixture'];
	        // ....
	    }
```

## Преимущества ##

- автоматическое удаление фикстур, об организации которого нужно побеспокоиться только 1 раз на этапе создания метода, для подъема фикстуры
- тесты не захламляются большим количеством кода для поднятия фикстур и за счет этого мы получаем более чистые и понятные тесты
- фикстуры для БД каждого модуля собраны в одном месте и ими проще управлять
- получение динамически созданных фикстур
