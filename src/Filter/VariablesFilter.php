<?php

declare(strict_types = 1);

namespace WebLoader\Filter;

use WebLoader\InvalidArgumentException;

/**
 * Variables filter for WebLoader
 *
 * @author Jan Marek
 * @license MIT
 */
class VariablesFilter
{

	private string $startVariable = '{{$';
	private string $endVariable = '}}';
	private array $variables = [];


	public function __construct(array $variables = [])
	{
		foreach ($variables as $key => $value) {
			$this->$key = $value;
		}
	}


	public function setDelimiter(string $start, string $end): self
	{
		$this->startVariable = (string) $start;
		$this->endVariable = (string) $end;
		return $this;
	}


	public function __invoke(string $code): string
	{
		$start = $this->startVariable;
		$end = $this->endVariable;

		$variables = array_map(function ($key) use ($start, $end) {
			return $start . $key . $end;
		}, array_keys($this->variables));

		$values = array_values($this->variables);

		return str_replace($variables, $values, $code);
	}


	/**
	 * Magic set variable, do not call directly
	 */
	public function __set(string $name, string $value): void
	{
		$this->variables[$name] = (string) $value;
	}


	/**
	 * Magic get variable, do not call directly
	 *
	 * @throws InvalidArgumentException
	 */
	public function &__get(string $name): string
	{
		if (array_key_exists($name, $this->variables)) {
			return $this->variables[$name];
		} else {
			throw new InvalidArgumentException("Variable '$name' is not set.");
		}
	}
}
