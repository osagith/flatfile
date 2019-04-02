<?php

namespace Osagith\Part\FlatFile;

class Field
{
    private $inputData = [];

    /**
     * Left justify field result
     */
    const PAD_LEFT = -1;
    /**
     * Right justify field result
     */
    const PAD_RIGHT = 0;
    /**
     * Number of points after decimal
     */
    const PRECISION = 2;
    /**
     * The decimal character
     */
    const DECIMAL = '.';
    /**
     * Thousands separator
     */
    const THOUSAND_SEPARATOR = ',';
    /**
     * Enable debug output
     *
     * @var integer
     */
    public static $DEBUG_MODE = 0;
    /**
     * Start column position
     *
     * @var int
     */
    private $start;
    /**
     * Maximum width of field
     * This will default to the length of value
     *
     * @var int
     */
    private $width;
    /**
     * Data type
     *
     * @var string
     */
    private $type;
    /**
     * Floating point precision
     *
     * @var int
     */
    private $precision;
    /**
     * Field value
     *
     * @var mixed
     */
    public $value;
    /**
     * Field name; header value
     *
     * @var string
     */
    private $name;
    /**
     * Formatted string
     *
     * @var string
     */
    private $result;
    /**
     * Pad direction
     * negative number, pad left
     * positive number, pad right
     *
     * @var int
     */
    private $pad = 0;
    
    /**
     * The padding character(s). Default is 0.
     *
     * @var string
     */
    private $char = 0;
    
    /**
     * Apply a callable function to value
     *
     * @var callable
     */
    private $callable;
    
    /**
     * Post callback function applied after format
     *
     * @var array
     */
    private $callback = [];
    /**
     * Supported Data Types
     *
     * string   value is treated as a string (default)
     * integer  value is treated as an integer; presented as a number
     * float    value is treated as a float; presented as a floating-point number
     * sfloat   value is treated as a float; presented as a number and the decimal point removed
     * currency value is treated as a float; presented as a double (future feature)
     * blank    value is treated as empty string; presented as a string padded to width
     * void     value is treated as null; field will be ignored
     *
     * @var array
     */
    protected $dataType = ['string' => 's','integer'=> 'd','float'=>'f', 'sfloat'=>'f', 'currency'=>'f', 'blank'=>'s', 'void'=> 's'];
    
    /**
     * Field data type map
     *
     * @see Field::$dataType    For the Field data type
     * @see Field::realType()   To determine the PHP data type
     * @var array
     */
    protected $realTypes = ['s'=>'string','f' => 'double','d' => 'integer'];

    public function __construct($args = null)
    {
        if (!is_null($args) && !is_array($args)) {
            throw new \InvalidArgumentException('Invalid field arguments');
        } else {
            $this->inputData = $args;
        }
    }

    private function setField()
    {
        if (!empty($this->inputData)) {
            foreach ($this->inputData as $property => $value) {
                $property = strtolower($property);
                if (property_exists($this, $property)) {
                    $setter = 'set'.\ucfirst($property);
                    if (method_exists($this, $setter)) {
                        $this->$setter($value);
                    }
                }
            }
        }
    }

    /**
     * Define the field maximum width
     *
     * @param int $width
     * @return $this
     */
    public function setWidth($width)
    {
        $this->width = (int)$width;
        return $this;
    }

    /**
     * Set the starting column position
     *
     * @see Field::$start
     * @param int $start
     * @return $this
     */
    public function setStart($start)
    {
        $this->start = (int)$start;
        return $this;
    }

    /**
     * Set field value
     *
     * @param string|int $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Get the maximum field width
     *
     * @return int
     */
    public function width()
    {
        if (empty($this->width)) {
            $this->width = strlen($this->value);
        }
        return $this->width;
    }

     /**
     * Get the unformatted field value
     *
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }
    /**
     * Get the column start position
     *
     * @return int
     */
    public function start()
    {
        return $this->start;
    }

    /**
     * Set the field name used to verify header
     *
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the floating point precision
     * The number of points after the decimal point
     *
     * @param int $points
     * @return $this
     */
    public function setPrecision($points)
    {
        $this->precision = (int)$points;
        return $this;
    }

     /**
     * Set the pad character
     *
     * @param string $char Single character to fill padding
     * @return $this
     */
    public function setChar($char)
    {
        if (strlen($char) > 1) {
            throw new \LengthException('Invalid character: '.$char.' padding character must be a char of width 1.',400);
        }
        $this->char = (string)$char;
        return $this;
    }

    /**
     * Set pad justification
     *
     * @param int $d
     * @return $this
     */
    public function setPad($d)
    {
        /** Run only if argument is a real string */
        if (!is_numeric($d) || is_bool($d)) {
            $d = strtolower($d);
            if ('right' == $d || 'pad_right' == $d || 'false' == $d || false === $d){
                $d = self::PAD_RIGHT;
            }
            if ('left' == $d || 'pad_left' == $d || 'true' == $d || true === $d){
                $d = self::PAD_LEFT;
            }
        }
        $this->pad = $d;
        return $this;
    }

    /**
     * Assign a Field data type
     * Defaults data type to string and precision level to Field::PRECISION
     *
     * @see Field::$dataType
     * @param string $type
     * @throws \LogicException
     * @return $this
     */
    public function setType($type)
    {
        $type = strtolower($type);
        try {
            if (!array_key_exists($type, $this->dataType)) {
                /** Default type if invalid type */
                $this->type = 'string';
                /** Sets type as NULL for exception output */
                $type = is_null($type) ? 'NULL' : $this->type;
                throw new \InvalidArgumentException('Invalid field type: '.$type. ' default to string',200);
            } else {
                $this->type = $type;
                /** Sets default precision if type is a double type */
                if (empty($this->precision)) {
                    $this->precision = ('double' === $this->realType()) ? self::PRECISION : null;
                }
            }
        } catch (\InvalidArgumentException $e) {
             printf("%s\n%s\n", $e->getMessage(), $e->getTraceAsString());
        }
        $this->typeFlag = 0;
        return $this;
    }

    /**
     * Set current decimal
     *
     * @param string $dec
     * @return $this
     */
    public function setDecimal($dec)
    {
        //TODO:  . || , only
        if (strlen($dec) > 1){
            throw new \LengthException('Invalid character: '.$dec.' decimal character must be a char of width 1.',400);
        }
        $this->decimal = $dec;
        return $this;
    }

    /**
     * Set thousand separator
     *
     * @param string $sep
     * @return $this
     */
    public function setThousandSeparator($sep)
    {
        //TODO:  . || , only
        if (strlen($sep) > 1){
            throw new \LengthException('Invalid character: '.$sep.' padding character must be a char of width 1.',400);
        }
        $this->thousandSeparator = $sep;
        return $this;
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }

    public function type()
    {
        return $this->type;
    }

    public function realType()
    {
        return $this->realTypes[$this->dataType[$this->type]];
    }

    /**
     * Returns formatted string width
     * Returns false if value is not formatted
     *
     * @return int|false
     */
    public function resultWidth()
    {
        if (empty($this->result)) {
            return false;
        }
        return strlen($this->result);
    }

    private function format()
    {
        /** Determine justification */
        $align = $this->pad < 0 ? '-' : '';
        $width = $this->width();
        /** Set precision */
        $precision = $this->precision ?: $this->width;

        return "%'".$this->char.$align.$width.'.'.$precision.$this->dataType[$this->type];
    }

    public function get()
    {
        if (empty($this->value) || $this->type == 'void') {
            return false;
        }
    }

    public function validate()
    {
        try {
            /** Validate value */
            if (empty($this->value)) {
                $this->type = 'blank';
            }
            if (!empty($this->value) && empty($this->width)) {
                throw new \LengthException('Field width not defined set to '.$this->width);
            }
            if (strlen($this->value) > $this->width) {
                throw new \LengthException('Value truncated: maximum width '.$this->width, 300);
            }
            /** @see Field::setType() */
            if (gettype($this->value) !== $this->realType() && ($this->type !== 'void' && $this->type !== 'blank')) {
                throw new \DomainException('Type mismatch '.gettype($this->value).' given type is '.$this->realType(), 100);
            }
            /** Validate precision */
            if ('double' !== $this->realType() && $this->typeFlag == 0) {
                throw new \InvalidArgumentException('Cannot set float precision on type '.$this->type(), 100);
            }
            if (!is_int($this->precision)) {
                throw new \InvalidArgumentException('Float precision must be an integer');
            }
        } catch (\LogicException $e) {
             printf("%s\n", $e->getMessage());
        }
        return $this;
    }

    /**
     * Set a callback function to apply to field value
     *
     * @param string $callable
     * @throws BadFunctionCallException
     * @return void
     */
    public function setCallable($callable)
    {
        try {
            if ($this->isCallable($callable)) {
                $this->callable = $callable;
            }
        } catch (\BadFunctionCallException $e) {
             echo $e->getMessage();
        }
        return $this;
    }

    /**
     * Test if callback function is_callable
     *
     * @param string $callable
     * @return boolean
     */
    private function isCallable($callable)
    {
        if (!is_callable($callable) && !empty($callable)) {
            throw new \BadFunctionCallException('Cannot call Function: '.$callable.' is not callable', 400);
            return false;
        }
        return true;
    }

    /**
     * Callback function
     *
     * @return void
     */
    public function callable()
    {
        return $this->callable;
    }

    public function postCallable($callback)
    {
        try {
            if ($this->isCallable($callback)) {
                $this->callback[] = $callback;
            }
        } catch (\BadFunctionCallException $e) {
             echo $e->getMessage();
        }
        
        return $this;
    }

    public function __toString()
    {
        return $this->get();
    }

    public function __invoke()
    {
        return $this->get();
    }
}
