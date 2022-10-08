<?php

namespace Sebk\SmallOrmCore\Generator\Bean;

use Plural\Plural;
use Sebk\SmallOrmCore\Dao\Field;
use Sebk\SmallOrmCore\Generator\DbGateway;
use Sebk\SmallOrmCore\Generator\Selector;
use Sebk\SmallOrmCore\Generator\TableNotFoundException;

class AbstractPhpFile
{

    protected $namespace;
    protected string $filepath;
    protected string $fileContent = "";

    /**
     * @param string $table
     * @param Selector $selector
     * @param Selector[] $selectors
     * @param array|null $removeTableNamespace
     * @param DbGateway $dbGateway
     */
    public function __construct(
        protected string $table,
        protected Selector $selector,
        protected array $selectors,
        protected array | null $removeTableNamespace,
        protected DbGateway $dbGateway
    )
    {
        if ($this instanceof DaoFile) {
            $directory = $this->selector->getDaoFolder();
            $this->namespace = $this->selector->getDaoNamespace();
        } else {
            $directory = $this->selector->getModelFolder();
            $this->namespace = $this->selector->getModelNamespace();
        }

        $this->filepath = $directory . "/" . static::getClassName($this->table) . ".php";
        if ($this->removeTableNamespace == null) {
            $this->removeTableNamespace = [];
        }
        Plural::loadLanguage('en');
    }

    /**
     * Camelize string
     * @param string $string
     * @param bool $firstLetterLowercase
     * @param bool $pluralize
     * @return string
     */
    protected static function camelize(string $string, bool $firstLetterLowercase = false, bool $pluralize = false): string
    {
        $parts = explode("_", $string);

        $result = "";
        foreach ($parts as $part) {
            if($pluralize) {
                $result .= ucfirst(static::pluralize($part));
            } else {
                $result .= ucfirst($part);
            }
        }

        if($firstLetterLowercase) {
            $result = lcfirst($result);
        }

        return $result;
    }

    /**
     * Return plural of a string
     * @param string $singular
     * @return string
     */
    protected static function pluralize(string $singular): string {
        return Plural::pluralize($singular);
    }

    /**
     * Convert a sql description record to php type
     * @param array $description
     * @return string
     */
    protected function getPhpType(array $fieldDescription): string
    {
        // Get sql type
        $sqlType = $fieldDescription["Type"];

        // INT(1) is considered as boolean
        if (strtolower($sqlType) == "int(1)") {
            return Field::TYPE_BOOLEAN;
        }

        // Remove brackets
        for($i = 0; $i < strlen($sqlType); $i++) {
            if (substr($sqlType, $i, 1) == "(") {
                break;
            }
        }
        $sqlType = substr($sqlType, 0, $i);

        switch(strtolower($sqlType)) {
            case "int":
            case "smallint":
            case "mediumint":
            case "bigint":
            case "double":
                return 'int';
            // TINYINT is considered as boolean
            case "tinyint":
                return 'bool';
            case "datetime":
                return '\DateTime';
            case "date":
                return '\DateTime';
            case "json":
                return 'array';
            case "decimal":
            case "float":
                return 'float';
            case "char":
            case "longtext":
            case "mediumtext":
            case "text":
            case "varchar":
            default:
                return 'string';
        }
    }

    /**
     * Get standard dao class name
     * @param string $table
     * @param array|null $removeTableNameSpaces
     * @return string
     */
    public function getClassname() {
        return $this->getClassnameForTable($this->table);
    }

    public function getClassnameForTable($table, $pluralize = false)
    {
        foreach ($this->removeTableNamespace as $namespace) {
            if(substr($table, 0, strlen($namespace)) == $namespace) {
                $table = substr($table, strlen($namespace));
            }
        }

        return static::camelize($table, false, $pluralize);
    }

    /**
     * Put content in dao file
     * @param $dbTableName
     * @param $content
     * @return $this
     */
    public function putFileContent(string|null $content = null): AbstractPhpFile
    {
        if ($content != null) {
            $this->fileContent = $content;
        }
        file_put_contents($this->filepath, $this->fileContent);

        return $this;
    }

    /**
     * Is file exists
     * @return bool
     */
    public function exists(): bool
    {
        return file_exists($this->filepath);
    }

    /**
     * Get DAO field name
     * @param string $dbFieldname
     * @return string
     */
    public static function getDaoFieldname(string $dbFieldname, bool $pluralize = false): string
    {
        return static::camelize($dbFieldname, true, $pluralize);
    }

    /**
     * @param string $table
     * @return Selector
     * @throws TableNotFoundException
     */
    protected function getSelectorForTable(string $table)
    {
        // Is current selector has table ?
        if (file_exists($this->selector->getDaoFolder() . '/' . $this->getClassname($table) . '.php')) {
            return $this->selector;
        }

        // Else get first selector which have table
        foreach ($this->selectors as $selector) {
            if (file_exists($selector->getDaoFolder() . '/' . $this->getClassname($table))) {
                return $selector;
            }
        }

        throw new TableNotFoundException('Table ' . $table . ' can\'t be found in any selector namespace');
    }

    /**
     * Get namespace for table
     * @param string $table
     * @return string
     * @throws TableNotFoundException
     */
    public function getNamepaceForTable(string $table)
    {
        $selector = $this->getSelectorForTable($table);
        if ($this instanceof DaoFile) {
            return $selector->getDaoNamespace();
        }

        return $selector->getModelNamespace();
    }


}