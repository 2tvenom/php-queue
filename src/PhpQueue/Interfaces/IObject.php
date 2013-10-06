<?php
namespace PhpQueue\Interfaces;

use PhpQueue\Exceptions\IObjectException;

/**
 * @property array fields
 * @property mixed exclude_fields
 */
abstract class IObject
{
    private static $SETTER_PREFIX = "setter";
    private static $GETTER_PREFIX = "getter";

    /**
     * Get value by name
     * @param string|string[] $name
     * @throws \PhpQueue\Exceptions\IObjectException
     * @return mixed
     */
    public function get($name){
        if(is_array($name))
        {
            $get_array = array();
            foreach($name as $field)
            {
                $getter_method = self::$GETTER_PREFIX . '_' . $field;
                if (method_exists($this, $getter_method)) {
                    $get_array[$field] = call_user_func_array(array($this, $getter_method), array($this->fields[$field]));
                    continue;
                }

                if (is_numeric($this->fields[$field])) {
                    $get_array[$field] = (int)$this->fields[$field];
                    continue;
                }

                $get_array[$field] = $this->fields[$field];
            }
            return $get_array;
        }

        return array_shift($this->get(array($name)));
    }

    /**
     * Set value by name
     * @param string|array $field
     * @param $value
     * @throws \PhpQueue\Exceptions\IObjectException
     * @return mixed
     */
    public function set($field, $value = null)
    {
        if(!is_array($field)) {
            $field = array($field => $value);
        }

        foreach ($field as $entity_name => $entity_value) {
            $filter_method = self::$SETTER_PREFIX . '_' . $entity_name;

            if (method_exists($this, $filter_method)) {
                $entity_value = call_user_func_array(array($this, $filter_method), array($entity_value));
            }

            $this->fields[$entity_name] = $entity_value;
        }

        return $this;
    }

    /**
     * @api
     * Getter and setter task data
     * @param $name
     * @param $arguments
     * @return $this
     * @throws \PhpQueue\Exceptions\IObjectException
     */
    public function __call($name, $arguments)
    {
        list($type, $field) = explode('_', $name, 2);


        if (!in_array($type, array('get', 'set', 'clear', 'inc', 'dec', 'null')) || !array_key_exists($field, $this->fields)) {
            throw new IObjectException("Function " . $name . " not found");
        }

        if(property_exists($this, 'exclude_fields')){
            if (in_array($field, $this->exclude_fields)) throw new IObjectException("Method not found");
        }


        if ($type == 'clear') {
            $this->fields[$field] = null;

            return $this;
        }

        if ($type == 'inc') {
            $this->fields[$field]+=1;
            return $this->fields[$field];
        }

        if ($type == 'dec') {
            $this->fields[$field] = 1;
            return $this->fields[$field];
        }

        if ($type == 'null') {
            $this->fields[$field] = null;
            return $this;
        }

        if ($type == 'get') {
            return $this->get($field);
        }

        if ($type == 'set') {
            if (count($arguments) != 1) throw new IObjectException("One argument required");

            return $this->set($field, $arguments[0]);
        }

        return $this;
    }


    /**
     * Serialize task settings
     * @return string
     */
    public function to_serialized()
    {
        return serialize($this);
    }

    /**
     * Return as array
     * @return array
     */
    public function to_array()
    {
        return $this->fields;
    }
}