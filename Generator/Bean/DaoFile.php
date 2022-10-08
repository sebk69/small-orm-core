<?php

namespace Sebk\SmallOrmCore\Generator\Bean;

use Sebk\SmallOrmCore\Dao\Field;
use Sebk\SmallOrmCore\Generator\TableNotFoundException;

class DaoFile extends AbstractPhpFile
{

    protected static $daoTemplate = "<?php
namespace [nameSpace];

use Sebk\\SmallOrmCore\\Dao\\AbstractDao;
use Sebk\\SmallOrmCore\\Dao\\Field;

class [daoName] extends AbstractDao
{
    protected function build()
    {
    }
}";

    /**
     * Get content of dao file
     * @return string
     */
    public function getFileContent(): string
    {
        $filePath = $this->filepath;
        if(file_exists($this->filepath)) {
            $this->fileContent = file_get_contents($this->filepath);
        } else {
            $template = str_replace("[nameSpace]", $this->namespace, static::$daoTemplate);
            $this->fileContent = str_replace("[daoName]", static::getClassName($this->table), $template);
        }

        return $this->fileContent;
    }

    /**
     * Generate build function
     * @param string $dbTableName
     * @return string
     */
    public function generateBuildFunction(): string
    {
        // retreive database description
        $description = $this->dbGateway->getDescription($this->table);

        // build function
        $output = "protected function build()
    {
        \$this->setDbTableName('" . $this->table . "')
            ->setModelClass(\\" . $this->selector->getModelNamespace() . "\\" . $this->getClassname()."::class)
";

        // Fields
        foreach ($description as $record) {
            // Get field description
            $description = new FieldDescription($record);

            // Get type
            $type = $description->getDaoType();

            // Set field builder
            $dbFieldName = $description->getFieldName();
            if($record["Key"] != "PRI") {
                $output .= "            ->addField('" .
                    $dbFieldName . "', '" .
                    static::camelize($dbFieldName, true) . "', " .
                    $description->getDefaultValue().', Field::' . $type . ')
';
            } else {
                $output .= "            ->addPrimaryKey('" . $dbFieldName . "', '" . static::camelize($dbFieldName, true) . "')
";
            }

        }

        // To one relations
        foreach ($this->dbGateway->getToOnes($this->table) as $toOne) {
            try {
                $output .= "            ->addToOne('" . lcfirst(static::getClassnameForTable($toOne["toTable"])) .
                    "', ['" . static::getDaoFieldname($toOne["toField"]) . "' => '" .
                    static::getDaoFieldname($toOne["fromField"]) . "'], \\" .
                    static::getNamepaceForTable($toOne["toTable"]). "\\" . $this->getClassnameForTable($toOne["toTable"]) . "::class)\n";
            } catch(TableNotFoundException $e) {

            }
        }

        // To many relations
        foreach ($this->dbGateway->getToManys($this->table) as $toMany) {
            try {
                $output .= "            ->addToMany('" . static::pluralize(lcfirst($this->getClassnameForTable($toMany["toTable"]))) .
                    "', ['" . static::getDaoFieldname($toMany["toField"]) . "' => '" .
                    static::getDaoFieldname($toMany["fromField"]). "'], \\" .
                    static::getNamepaceForTable($toOne["toTable"]) . "\\" . $this->getClassnameForTable($toMany["toTable"]) . "::class)\n";
            } catch(TableNotFoundException $e) {}
        }

        $output .= '        ;
    }';

        return $output;
    }

}