<?php

namespace MailQ\Entities;

use Nette\Reflection\ClassType;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class BaseEntity {

    use SmartObject;

    const INVERT_NAMES = true;

    /**
     * @var ArrayHash
     */
    private $attributeNames;

    /**
     * Create new entity from array or string which is JSON
     * @param string|array $data
     * @param bool $inverse
     * @throws \Nette\Utils\JsonException
     */
    public function __construct($data = NULL, $inverse = false) {
        if ($data !== null) {
            if (is_string($data)) {
                $data = Json::decode($data);
            }
            $this->initMapping($inverse ? 'out' : 'in');
            foreach ($data as $key => $value) {
                if ($value instanceof \stdClass) {
                    $value = (array) $value;
                }
                $reflection = new ClassType($this);
                if ($this->attributeNames->offsetExists($key)) {
                    $propertyName = $this->attributeNames->offsetGet($key);
                    if ($reflection->hasProperty($propertyName)) {
                        $property = $reflection->getProperty($propertyName);
                        $type = $property->getAnnotation('var');
                        if (Strings::endsWith($type, 'Entity') || Strings::endsWith($type, 'Entity[]')) {
                            $className = Strings::replace($type,'~\\[\\]~i');
                            $classWithNamespace = sprintf("\\%s\\%s", $reflection->getNamespaceName(), (string) $className);
                            if (is_array($value) && $property->hasAnnotation('collection')) {
                                $arrayData = array();
                                foreach ($value as $valueData) {
                                    $arrayData[] = new $classWithNamespace($valueData, $inverse);
                                }
                                $this->$propertyName = $arrayData;
                            } else {
                                $this->$propertyName = new $classWithNamespace($value);
                            }
                        } else {
                            $this->$propertyName = $value;
                        }
                    }
                }
            }
        }
    }

    /**
     * Creates ArrayHash where key is in annotation name
     * and value is property name
     * It is used to find property name by in annotation value
     * @param $mapping
     */
    private function initMapping($mapping) {
        $this->attributeNames = new ArrayHash();
        $reflection = new ClassType($this);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            if ($property->hasAnnotation($mapping)) {
                $annotation = $property->getAnnotation($mapping);
                if (is_string($annotation)) {
                    $this->attributeNames[$annotation] = $property->getName();
                } else {
                    $this->attributeNames[$property->getName()] = $property->getName();
                }
            }
        }
    }

    public function toArray($inverse = false) {
        $data = array();
        $mapping = $inverse ? 'in' : 'out';
        $reflection = new ClassType($this);
        $properties = $reflection->getProperties();
        foreach ($properties as $key => $property) {
            if ($property->hasAnnotation($mapping)) {
                $propertyName = $property->getName();
                $annotation = $property->getAnnotation($mapping);
                if (is_string($annotation)) {
                    $outputName = $annotation;
                } else {
                    $outputName = $property->getName();
                }
                $value = $this->$propertyName;
                if ($value instanceof BaseEntity) {
                    $data[$outputName] = $value->toArray($inverse);
                } else if (is_array($value) && $property->hasAnnotation('collection')) {
                    $array = array();
                    foreach ($value as $item) {
                        if ($item instanceof BaseEntity) {
                            $array[] = $item->toArray($inverse);
                        }
                    }
                    $data[$outputName] = $array;
                } else if ($value !== NULL) {
                    $data[$outputName] = $value;
                }
            }
        }
        if (count($data) == 0) {
            return (object) $data;
        } else {
            return $data;
        }
    }

}
