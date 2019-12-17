<?php declare(strict_types = 1);

namespace PHPStan\Analyser;

use PHPStan\Broker\Broker;
use PHPStan\DependencyInjection\Container;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\PropertyReflectionFinder;

class ScopeFactory
{

	/** @var string */
	private $scopeClass;

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	/** @var \PHPStan\Reflection\ReflectionProvider */
	private $reflectionProvider;

	/** @var \PhpParser\PrettyPrinter\Standard */
	private $printer;

	/** @var \PHPStan\Analyser\TypeSpecifier */
	private $typeSpecifier;

	/** @var \PHPStan\Rules\Properties\PropertyReflectionFinder */
	private $propertyReflectionFinder;

	/** @var string[] */
	private $dynamicConstantNames;

	public function __construct(
		string $scopeClass,
		Broker $broker,
		ReflectionProvider $reflectionProvider,
		\PhpParser\PrettyPrinter\Standard $printer,
		TypeSpecifier $typeSpecifier,
		PropertyReflectionFinder $propertyReflectionFinder,
		Container $container
	)
	{
		$this->scopeClass = $scopeClass;
		$this->broker = $broker;
		$this->reflectionProvider = $reflectionProvider;
		$this->printer = $printer;
		$this->typeSpecifier = $typeSpecifier;
		$this->propertyReflectionFinder = $propertyReflectionFinder;
		$this->dynamicConstantNames = $container->getParameter('dynamicConstantNames');
	}

	/**
	 * @param \PHPStan\Analyser\ScopeContext $context
	 * @param bool $declareStrictTypes
	 * @param \PHPStan\Reflection\FunctionReflection|\PHPStan\Reflection\MethodReflection|null $function
	 * @param string|null $namespace
	 * @param \PHPStan\Analyser\VariableTypeHolder[] $variablesTypes
	 * @param \PHPStan\Analyser\VariableTypeHolder[] $moreSpecificTypes
	 * @param string|null $inClosureBindScopeClass
	 * @param \PHPStan\Reflection\ParametersAcceptor|null $anonymousFunctionReflection
	 * @param bool $inFirstLevelStatement
	 * @param array<string, true> $currentlyAssignedExpressions
	 *
	 * @return MutatingScope
	 */
	public function create(
		ScopeContext $context,
		bool $declareStrictTypes = false,
		$function = null,
		?string $namespace = null,
		array $variablesTypes = [],
		array $moreSpecificTypes = [],
		?string $inClosureBindScopeClass = null,
		?ParametersAcceptor $anonymousFunctionReflection = null,
		bool $inFirstLevelStatement = true,
		array $currentlyAssignedExpressions = []
	): MutatingScope
	{
		$scopeClass = $this->scopeClass;
		if (!is_a($scopeClass, MutatingScope::class, true)) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return new $scopeClass(
			$this,
			$this->broker,
			$this->reflectionProvider,
			$this->printer,
			$this->typeSpecifier,
			$this->propertyReflectionFinder,
			$context,
			$declareStrictTypes,
			$function,
			$namespace,
			$variablesTypes,
			$moreSpecificTypes,
			$inClosureBindScopeClass,
			$anonymousFunctionReflection,
			$inFirstLevelStatement,
			$currentlyAssignedExpressions,
			$this->dynamicConstantNames
		);
	}

}
