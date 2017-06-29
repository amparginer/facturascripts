<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Base;

/**
 * La clase de la que heredan todos los modelos, conecta a la base de datos,
 * comprueba la estructura de la tabla y de ser necesario la crea o adapta.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
trait Model {

    /**
     * Proporciona acceso directo a la base de datos.
     * @var DataBase
     */
    protected $dataBase;

    /**
     * Permite conectar e interactuar con el sistema de caché.
     * @var Cache
     */
    protected $cache;

    /**
     * Clase que se utiliza para definir algunos valores por defecto:
     * codejercicio, codserie, coddivisa, etc...
     * @var DefaultItems 
     */
    protected $defaultItems;

    /**
     * Lista de campos de la tabla.
     * @var mixed 
     */
    protected static $fields;

    /**
     * Traductor multi-idioma.
     * @var Translator 
     */
    protected $i18n;

    /**
     * Gestiona el log de todos los controladores, modelos y base de datos.
     * @var MiniLog
     */
    protected $miniLog;

    /**
     * Nombre del modelo. De la clase que inicia este trait.
     * @var string 
     */
    private static $modelName;

    /**
     * Nombre de la columna que es clave primaria.
     * @var string 
     */
    private static $primaryColumn;

    /**
     * Nombre de la tabla en la base de datos.
     * @var string 
     */
    private static $tableName;

    /**
     * Lista de tablas ya comprobadas.
     * @var array 
     */
    private static $checkedTables;

    /**
     * Constructor.
     * @param string $tableName nombre de la tabla de la base de datos.
     */
    private function init($modelName = '', $tableName = '', $primaryColumn = '') {
        $this->cache = new Cache();
        $this->dataBase = new DataBase();
        $this->defaultItems = new DefaultItems();
        $this->i18n = new Translator();
        $this->miniLog = new MiniLog();

        if (self::$checkedTables === NULL) {
            self::$checkedTables = $this->cache->get('fs_checked_tables');
            if (self::$checkedTables === NULL) {
                self::$checkedTables = [];
            }

            self::$modelName = $modelName;
            self::$primaryColumn = $primaryColumn;
            self::$tableName = $tableName;
        }

        if ($tableName != '' && !in_array($tableName, self::$checkedTables) && $this->checkTable($tableName)) {
            $this->miniLog->debug('Table ' . $tableName . ' checked.');
            self::$checkedTables[] = $tableName;
            $this->cache->set('fs_checked_tables', self::$checkedTables);
        }

        if (self::$fields === NULL) {
            self::$fields = ($this->dataBase->tableExists($tableName) ? $this->dataBase->getColumns($tableName) : []);
        }
    }

    /**
     * Devuelve el nombre del modelo.
     * @return string
     */
    public function modelName() {
        return self::$modelName;
    }

    /**
     * Devuelve el nombre de la columna que es clave primaria del modelo.
     * @return type
     */
    public function primaryColumn() {
        return self::$primaryColumn;
    }

    /**
     * Devuelve el nombdre de la tabla que usa este modelo.
     * @return string
     */
    public function tableName() {
        return self::$tableName;
    }

    /**
     * Asigna a las propiedades del modelo los valores del array $data
     * @param mixed $data
     */
    public function loadFromData($data = []) {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
            if ($value === NULL) {
                continue;
            }

            foreach (self::$fields as $field) {
                if ($field['name'] == $key) {
                    $type = strstr($field['type'], '(');
                    switch ($type) {
                        case 'tinyint':
                        case 'boolean':
                            $this->{$key} = $this->str2bool($value);
                            break;

                        case 'integer':
                        case 'int':
                            $this->{$key} = (int) $value;
                            break;

                        case 'double':
                        case 'float':
                            $this->{$key} = (float) $value;
                            break;

                        case 'date':
                            $this->{$key} = Date('d-m-Y', strtotime($value));
                            break;
                    }
                    break;
                }
            }
        }
    }

    /**
     * Resetea los valores de todas las propiedades modelo.
     */
    public function clear() {
        foreach (self::$fields as $field) {
            $this->{$field['name']} = NULL;
        }
    }

    /**
     * Esta función es llamada al crear la tabla del modelo. Devuelve el SQL
     * que se ejecutará tras la creación de la tabla. ütil para insertar valores
     * por defecto.
     * @return string
     */
    private function install() {
        return '';
    }

    /**
     * Devuelve el modelo cuya columna primaria corresponda al valor $cod
     * @param mixed $cod
     * @return mixed
     */
    public function get($cod) {
        $data = $this->dataBase->select("SELECT * FROM " . $this->tableName() . " WHERE " . $this->primaryColumn() . " = " . $this->var2str($cod) . ";");
        if ($data) {
            $class = $this->modelName();
            return new $class($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve el primer modelo que coincide con los filtros establecidos.
     * @param array $fields filtros a aplicar a los campos. Por ejemplo ['codserie' => 'A']
     * @return mixed
     */
    public function getBy($fields = []) {
        $sql = "SELECT * FROM " . $this->tableName();
        $coma = " WHERE ";

        foreach ($fields as $key => $value) {
            $sql .= $coma . $key . " = " . $this->var2str($value);
            if ($coma === " WHERE ") {
                $coma = ", ";
            }
        }

        $data = $this->dataBase->selectLimit($sql, 1);
        if ($data) {
            $class = $this->modelName();
            return new $class($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve true si los datos del modelo se encuentran almacenados en la base de datos.
     * @return boolean
     */
    public function exists() {
        if ($this->{$this->primaryColumn()} === NULL) {
            return FALSE;
        }

        return (bool) $this->dataBase->select("SELECT 1 FROM " . $this->tableName()
                        . " WHERE " . $this->primaryColumn() . " = " . $this->var2str($this->{$this->primaryColumn()}) . ";");
    }

    /**
     * Devuelve true si no hay errores en los valores de las propiedades del modelo.
     * Se ejecuta dentro del método save.
     * @return boolean
     */
    public function test() {
        return TRUE;
    }

    /**
     * Almacena los datos del modelo en la base de datos.
     * @return boolean
     */
    public function save() {
        if ($this->test()) {
            if ($this->exists()) {
                return $this->saveUpdate();
            }

            return $this->saveInsert();
        }

        return FALSE;
    }

    /**
     * Actualiza los datos del modelo en la base de datos.
     * @return boolean
     */
    private function saveUpdate() {
        $sql = "UPDATE " . $this->tableName();
        $coma = ' SET';

        foreach (self::$fields as $field) {
            if ($field['name'] !== $this->primaryColumn()) {
                $sql .= $coma . ' ' . $field['name'] . ' = ' . $this->var2str($this->{$field['name']});
                if ($coma === ' SET') {
                    $coma = ', ';
                }
            }
        }

        $sql .= " WHERE " . $this->primaryColumn() . " = " . $this->var2str($this->{$this->primaryColumn()}) . ";";
        return $this->dataBase->exec($sql);
    }

    /**
     * Inserta los datos del modelo en la base de datos.
     * @return boolean
     */
    private function saveInsert() {
        $insertFields = [];
        $insertValues = [];
        foreach (self::$fields as $field) {
            if ($this->{$field['name']} !== NULL) {
                $insertFields[] = $field['name'];
                $insertValues[] = $this->var2str($this->{$field['name']});
            }
        }

        $sql = "INSERT INTO " . $this->tableName() . " (" . implode(',', $insertFields) . ") VALUES (" . implode(',', $insertValues) . ");";
        if ($this->dataBase->exec($sql)) {
            if ($this->{$this->primaryColumn()} === NULL) {
                $this->{$this->primaryColumn()} = $this->dataBase->lastval();
            }

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Elimina los datos del modelo de la base de datos.
     * @return boolean
     */
    public function delete() {
        return $this->dataBase->exec("DELETE FROM " . $this->tableName()
                        . " WHERE " . $this->primaryColumn() . " = " . $this->var2str($this->{$this->primaryColumn()}) . ";");
    }

    /**
     * Devuelve todos los modelos que se correspondan con los filtros seleccionados.
     * @param array $fields filtros a aplicar a los campos. Por ejemplo ['codserie' => 'A']
     * @param array $order campos a utilizar en la ordenación. Por ejemplo ['codigo' => 'ASC']
     * @param integer $offset
     * @param integer $limit
     * @return mixed
     */
    public function all($fields = [], $order = [], $offset = 0, $limit = 50) {
        $modelList = [];
        $sql = "SELECT * FROM " . $this->tableName();
        $coma = " WHERE ";

        foreach ($fields as $key => $value) {
            $sql .= $coma . $key . " = " . $this->var2str($value);
            if ($coma === " WHERE ") {
                $coma = ", ";
            }
        }

        $coma2 = " ORDER BY ";
        foreach ($order as $key => $value) {
            $sql .= $coma2 . $key . " " . $this->var2str($value);
            if ($coma2 === " WHERE ") {
                $coma2 = ", ";
            }
        }

        $data = $this->dataBase->selectLimit($sql, $limit, $offset);
        if ($data) {
            $class = $this->modelName();
            foreach ($data as $d) {
                $modelList[] = new $class($d);
            }
        }

        return $modelList;
    }

    /**
     * Escapa las comillas de una cadena de texto.
     * @param string $str cadena de texto a escapar
     * @return string cadena de texto resultante
     */
    protected function escapeString($str) {
        return $this->dataBase->escapeString($str);
    }

    /**
     * Transforma una variable en una cadena de texto válida para ser
     * utilizada en una consulta SQL.
     * @param mixed $val
     * @return string
     */
    public function var2str($val) {
        if ($val === NULL) {
            return 'NULL';
        }

        if (is_bool($val)) {
            if ($val) {
                return 'TRUE';
            }
            return 'FALSE';
        }

        if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $val)) {
            return "'" . Date($this->dataBase->dateStyle(), strtotime($val)) . "'"; /// es una fecha
        }

        if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})$/i', $val)) {
            return "'" . Date($this->dataBase->dateStyle() . ' H:i:s', strtotime($val)) . "'"; /// es una fecha+hora
        }

        return "'" . $this->dataBase->escapeString($val) . "'";
    }

    /**
     * PostgreSQL guarda los valores TRUE como 't', MySQL como 1.
     * Esta función devuelve TRUE si el valor se corresponde con
     * alguno de los anteriores.
     * @param string $val
     * @return boolean
     */
    public function str2bool($val) {
        return ($val == 't' || $val == '1');
    }

    /**
     * Esta función convierte:
     * < en &lt;
     * > en &gt;
     * " en &quot;
     * ' en &#39;
     * 
     * No tengas la tentación de sustiturla por htmlentities o htmlspecialshars
     * porque te encontrarás con muchas sorpresas desagradables.
     * @param string $txt
     * @return string
     */
    public static function noHtml($txt) {
        $newt = str_replace(
                array('<', '>', '"', "'"), array('&lt;', '&gt;', '&quot;', '&#39;'), $txt
        );

        return trim($newt);
    }

    /**
     * Comprueba y actualiza la estructura de la tabla si es necesario
     * @param string $tableName
     * @return boolean
     */
    protected function checkTable($tableName) {
        $done = TRUE;
        $sql = '';
        $xmlCols = [];
        $xmlCons = [];

        if ($this->getXmlTable($tableName, $xmlCols, $xmlCons)) {
            if ($this->dataBase->tableExists($tableName)) {
                if (!$this->dataBase->checkTableAux($tableName)) {
                    $this->miniLog->critical('Error al convertir la tabla a InnoDB.');
                }

                /**
                 * Si hay que hacer cambios en las restricciones, eliminamos todas las restricciones,
                 * luego añadiremos las correctas. Lo hacemos así porque evita problemas en MySQL.
                 */
                $dbCons = $this->dataBase->getConstraints($tableName);
                $sql2 = $this->dataBase->compareConstraints($tableName, $xmlCons, $dbCons, TRUE);
                if ($sql2 != '') {
                    if (!$this->dataBase->exec($sql2)) {
                        $this->miniLog->critical('Error al comprobar la tabla ' . $tableName);
                    }

                    /// leemos de nuevo las restricciones
                    $dbCons = $this->dataBase->getConstraints($tableName);
                }

                /// comparamos las columnas
                $dbCols = $this->dataBase->getColumns($tableName);
                $sql .= $this->dataBase->compareColumns($tableName, $xmlCols, $dbCols);

                /// comparamos las restricciones
                $sql .= $this->dataBase->compareConstraints($tableName, $xmlCons, $dbCons);
            } else {
                /// generamos el sql para crear la tabla
                $sql .= $this->dataBase->generateTable($tableName, $xmlCols, $xmlCons);
                $sql .= $this->install();
            }

            if ($sql != '' && !$this->dataBase->exec($sql)) {
                $this->miniLog->critical('Error al comprobar la tabla ' . $tableName);
                $done = FALSE;
            }
        } else {
            $this->miniLog->critical('Error con el xml.');
            $done = FALSE;
        }

        return $done;
    }

    /**
     * Obtiene las columnas y restricciones del fichero xml para una tabla
     * @param string $tableName
     * @param array $columns
     * @param array $constraints
     * @return boolean
     */
    protected function getXmlTable($tableName, &$columns, &$constraints) {
        $return = FALSE;

        /// necesitamos el plugin manager para obtener la carpeta de trabajo de FacturaScripts
        $pluginManager = new PluginManager();

        $filename = $pluginManager->folder() . '/Dinamic/Table/' . $tableName . '.xml';
        if (file_exists($filename)) {
            $xml = simplexml_load_string(file_get_contents($filename, FILE_USE_INCLUDE_PATH));
            if ($xml) {
                if ($xml->columna) {
                    $key = 0;
                    foreach ($xml->columna as $col) {
                        $columns[$key]['nombre'] = (string) $col->nombre;
                        $columns[$key]['tipo'] = (string) $col->tipo;

                        $columns[$key]['nulo'] = 'YES';
                        if ($col->nulo && strtolower($col->nulo) == 'no') {
                            $columns[$key]['nulo'] = 'NO';
                        }

                        if ($col->defecto == '') {
                            $columns[$key]['defecto'] = NULL;
                        } else {
                            $columns[$key]['defecto'] = (string) $col->defecto;
                        }

                        $key++;
                    }

                    /// debe de haber columnas, sino es un fallo
                    $return = TRUE;
                }

                if ($xml->restriccion) {
                    $key = 0;
                    foreach ($xml->restriccion as $col) {
                        $constraints[$key]['nombre'] = (string) $col->nombre;
                        $constraints[$key]['consulta'] = (string) $col->consulta;
                        $key++;
                    }
                }
            } else {
                $this->miniLog->critical('Error al leer el archivo ' . $filename);
            }
        } else {
            $this->miniLog->critical('Archivo ' . $filename . ' no encontrado.');
        }

        return $return;
    }

}