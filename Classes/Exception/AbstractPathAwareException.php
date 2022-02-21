<?php
declare(strict_types=1);


namespace Neunerlei\Options\Exception;


use Throwable;

abstract class AbstractPathAwareException extends OptionException
{
    protected $pathErrorMessage = 'An error occurred at: "%s"; ';

    /**
     * The path in the node tree to where the exception occurred
     *
     * @var array
     */
    protected $path;

    public function __construct($message = '', ?array $path = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            (empty($path) ? '' : (sprintf($this->pathErrorMessage, implode('.', $path)))) .
            $message, $code, $previous);
        $this->path = $path ?? [];
    }

    /**
     * Returns the path in the node tree to where the exception occurred
     *
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }

}
