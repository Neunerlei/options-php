# Option Applier
This nifty little helper can be used to apply a schema to any given array.
It is designed to work as an on-the-fly validator for method options but is powerful enough to validate all sorts of API requests as well.

## Installation
Install this package using composer:

```
composer require neunerlei/options
```

## Basic Usage
The main purpose of the package is to validate options that are passed to methods or functions, like in this basic example:
```php
use Neunerlei\Options\Options;
function myFunc(array $options = []){
    // Apply the options
    $options = Options::make($options, [
        "foo" => 123,
        "bar" => null,
    ]);
    print_r($options);
}

myFunc(); // Prints: ["foo" => 123, "bar" => null]
myFunc(["bar" => "baz"]);  // Prints: ["foo" => 123, "bar" => "baz"]
myFunc(["baz" => 234]); // This will cause an exception, because the key "baz" is not known
``` 

## Simple Definition
As you can see above, you can define a simple list of keys and matching default values,
that act like "array_merge" would. All keys passed to $options that are not in the definition will throw a validation exception.
In general, you can pass any value as a default (arrays require a little quirk, tho (see below));

#### Defining a key without default
If you want your options array to have a key that is required and therefore does not have a default value, you can configure it as required like:
```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => ["required"]
]);
```
If "foo" could not be not found in your $options array, the method will throw a validation exception, telling you that the key is missing.
**NOTE: Make sure you wrap "required" in an array!** 

#### Defining an array as default value
Please note that it is not possible to pass an array as a default value like you would with any other type.
You have to make sure that your default array is wrapped by an outer array like:
```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => [["my" => "defaultValue"]]
]);
```
If you don't wrap the default array in an array a InvalidOptionDefinitionException will be thrown.

## Advanced Definition
In addition to the simple default values, you can also use an array as a value in your definitions array.
In it, you can set the following options to validate and manipulate the options to your liking.

I choose arrays as a definition because they run fast, without the overhead, and one is only required to remember a handful of options. If there is a need, I might add an object-oriented approach, so give me a shout if you are interested.

### Options

#### default _(mixed|callable)_

This is the default value to use when the key in $options is not given.
If not set, the option key is required! If the default value is a Closure, the closure is called, and its result is used as the value.
```php
use Neunerlei\Options\Options;

// Simple value default
$options = Options::make($options, [
    "foo" => [
        "default" => 123
    ]
]);

// Closure result value default
$options = Options::make($options, [
    "foo" => [
        "default" => function($key, $options, $keyDefinition, $path){
            return 123;
        }       
    ]
]);
```

#### type _(string|array)_
Allows basic type validation of the input. It can either be a string or an array of strings.
If multiple values are supplied as an array, they are seen as chained via OR operator. Possible values are: 

- boolean
- bool
- true
- false
- integer
- int
- double
- float
- number (int and float)
- numeric (both int and float + string numbers -> is_numeric)
- string
- resource
- null
- callable

It is also possible to validate the type of an instance based on a class or interface name.

```php
use Neunerlei\Options\Options;

// Simple types
$options = Options::make($options, [ "foo" => [ "type" => "string" ] ]);
$options = Options::make($options, [ "foo" => [ "type" => "number" ] ]);
$options = Options::make($options, [ "foo" => [ "type" => [ "number", "string" ] ] ]);

// Class types
interface AI {};
class A implements AI {}
class B extends A {}
$options = Options::make(["foo" => new A()], [ "foo" => [ "type" => [A::class]]]); // OK -> Same class
$options = Options::make(["foo" => new B()], [ "foo" => [ "type" => [A::class]]]); // OK -> A is the parent
$options = Options::make(["foo" => new B()], [ "foo" => [ "type" => [AI::class]]]); // OK -> AI is implemented by the parent
$options = Options::make(["foo" => new A()], [ "foo" => [ "type" => [B::class]]]); // FAIL
```

#### preFilter _(callable)_
A callback that is called **BEFORE** the type validation takes place and can be used to cast the incoming value before validating its type.

```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => [
        "preFilter" => function($incomingValue, $key, $options, $keyDefinition, $path){
            if(is_string($incomingValue)) return (int)$incomingValue;
            return $incomingValue;
        }
    ]
]);
```

#### filter _(callable)_
A callback to call after the type validation took place and can be used to process a given value before the custom validation begins.

```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => [
        "type" => "int",
        "filter" => function(int $incomingValue, $key, $options, $keyDefinition, $path){
            return empty($incomingValue) ? 1 : $incomingValue;
        }
    ]
]);
```

#### validator _(callable)_
A callback which allows custom validation using closures or other callables. If used, the
function should return ```TRUE``` if the validation was successful or ```FALSE``` if not. 

It is also possible to return a string on error, which allows you to set a custom error message. 

Finally you may return an array of values that will be passed to the "values" validator (see the next point for the functionality)

```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => [
        "type" => "int",
        "validator" => function(int $incomingValue, $key, $options, $keyDefinition, $path){
            return TRUE; // Success!
            return FALSE; // Failed
            return "Failed to validate something!"; // Failed with custom error message
            return [123, 234]; // Let the "values" validator decide
        }
    ]
]);
```

#### values _(array)_
A basic validation routine that receives a list of possible values and checks if the given value was defined (OR operator). The array can either be set statically
in your definition, or by using a "validator" callback that returns an array of possible values. The values validation takes place after the "validator" callback ran.

```php
use Neunerlei\Options\Options;
$options = Options::make($options, [
    "foo" => [
        "type" => "int",
        "validator" => [123, 234] // Only 123 or 234 are allowed values
    ]
]);
```

#### children _(array)_
This can be used to apply nested definitions on option trees. The description of child options is done the same way you would in the root object.
When running filters, validators, or other callbacks, you probably noticed the ```$path``` argument. It gives you the path through the tree to the current node as an array of keys.
NOTE: The children will only be used if the value in $options is an array (or has a default value of an empty array).
    
Validating a direct, associative child of a node:
```php
use Neunerlei\Options\Options;
$options = Options::make([], [
    "foo" => [
        "type" => "array",
        "default" => [],
        "children" => [
            "childFoo" => 123,
            "KongFoo" => [
                "type" => "string", 
                "default" => "foo!"
            ]
        ]
    ]
]);

// $options will look like:
// [
//     "foo" => [
//         "childFoo" => 123,
//         "KongFoo" => "foo!"    
//     ]
// ]
```

Validating a list of child nodes that have the same structure:
```php
use Neunerlei\Options\Options;
$options = [
    "foo" => [
        ["childFoo" => 234],
        ["KongFoo" => "bar :D"]
    ]
];

$options = Options::make($options, [
    "foo" => [
        "type" => "array",
        "default" => [],
        "children" => [
            // This asterisk defines that the children are repeatable 
            "*" => [
                "childFoo" => 123,
                "KongFoo" => [
                    "type" => "string", 
                    "default" => "foo!"
                ]
            ]
        ]
    ]
]);

// $options will look like:
// [
//     "foo" => [
//         ["childFoo" => 234, "KongFoo" => "foo!"],
//         ["childFoo" => 123, "KongFoo" => "bar :D"]
//     ]
// ];
```

## Boolean Flags
It is also possible to supply options that have a type of "boolean" as "flags," which means you don't have to provide any values to it.
NOTE: Boolean Flags can only be used to set a boolean value to TRUE if you want to set it to FALSE you have to set the key, value pair.

```php
use Neunerlei\Options\Options;
function myFunc(array $options = []){
    // Apply the options
    $options = Options::make($options, [
        "foo" => [
            "type" => "bool",
            "default" => false
        ]
    ]);
    print_r($options);
}

myFunc(); // Prints: ["foo" => false]
myFunc(["foo"]); // Prints: ["foo" => true]
myFunc(["foo" => true]); // Prints: ["foo" => true]
```

## Additional options
The third parameter of the Options::make() method lets you define additional options.
All boolean values can be either passed as key-value pairs or as boolean flags.

#### allowUnknown (bool)
_DEFAULT: FALSE_

If set to TRUE, unknown keys will be kept in the result.
 
#### ignoreUnknown (bool)
_DEFAULT: FALSE_

If set to TRUE, unknown keys will be ignored but removed from the result.
 
#### allowBooleanFlags (bool)
_DEFAULT: TRUE_

If set to FALSE, it is not allowed to use boolean flags in your input array. Useful when validating API inputs.

## Single value handling
In general, this does the same as Options::make() but is designed to validate non-array options.

NOTE: There is one gotcha. As you see in our example, we define $anOption as = null in the signature.
This will cause the method to use the default value of "foo" if the property is not set.
So make sure, if you want to allow NULL as non-default value to use a callback as default and handle null on your own.

```php
use Neunerlei\Options\Options;
function myFunc($value, $anOption = null){
   $anOption = Options::makeSingle("anOption", $anOption, [
       "type" => ["string"],
       "default" => "foo",
   ]);
}
```

## Usage without static class
The static class uses a singleton of the ```Neunerlei\Options\OptionApplier``` class for all its actions.
So if you want to use the applier as a service using dependency injection, just use the applier class instead of the static Options class.

## Extending the applier class
The static Options class has a public, static property called ```$applierClass```, which defines the name of the class used for the logic.
If you should ever want to extend the functionality, you can simply extend the applier class and set ```Options::$applierClass``` to the name of your extended class and will be good to go.

## Special Thanks
Special thanks go to the folks at [LABOR.digital](https://labor.digital/) (which is the german word for laboratory and not the English "work" :D) for making it possible to publish my code online.

## Postcardware
You're free to use this package, but if it makes it to your production environment, I highly appreciate you sending me a postcard from your hometown, mentioning which of our package(s) you are using.

You can find my address [here](https://www.neunerlei.eu/). 

Thank you :D 
