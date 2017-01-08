<?php declare(strict_types = 1);

namespace PHPStan;

use Nette\DI\Container;
use PHPStan\Broker\Broker;
use PHPStan\Parser\DirectParser;
use PHPStan\Parser\FunctionCallStatementFinder;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\FunctionReflectionFactory;
use PHPStan\Reflection\Php\PhpClassReflectionExtension;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Reflection\Php\PhpMethodReflectionFactory;
use PHPStan\Reflection\Php\UniversalObjectCratesClassReflectionExtension;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\Type;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var \Nette\DI\Container
	 */
	private static $container;

	/** @var \PHPStan\Parser\Parser */
	private $parser;

	public function getContainer(): \Nette\DI\Container
	{
		return self::$container;
	}

	public static function setContainer(Container $container)
	{
		self::$container = $container;
	}

	public function getParser(): \PHPStan\Parser\Parser
	{
		if ($this->parser === null) {
			$traverser = new \PhpParser\NodeTraverser();
			$traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
			$this->parser = new DirectParser(new \PhpParser\Parser\Php7(new \PhpParser\Lexer()), $traverser);
		}

		return $this->parser;
	}

	/**
	 * @param \PHPStan\Type\DynamicMethodReturnTypeExtension[] $dynamicMethodReturnTypeExtensions
	 * @param \PHPStan\Type\DynamicStaticMethodReturnTypeExtension[] $dynamicStaticMethodReturnTypeExtensions
	 * @return \PHPStan\Broker\Broker
	 */
	public function createBroker(array $dynamicMethodReturnTypeExtensions = [], array $dynamicStaticMethodReturnTypeExtensions = []): Broker
	{
		$functionCallStatementFinder = new FunctionCallStatementFinder();
		$parser = $this->getParser();
		$cache = new \Nette\Caching\Cache(new \Nette\Caching\Storages\MemoryStorage());
		$phpExtension = new PhpClassReflectionExtension(new class($parser, $functionCallStatementFinder, $cache) implements PhpMethodReflectionFactory {
			/** @var \PHPStan\Parser\Parser */
			private $parser;

			/** @var \PHPStan\Parser\FunctionCallStatementFinder */
			private $functionCallStatementFinder;

			/** @var \Nette\Caching\Cache */
			private $cache;

			public function __construct(
				Parser $parser,
				FunctionCallStatementFinder $functionCallStatementFinder,
				\Nette\Caching\Cache $cache
			)
			{
				$this->parser = $parser;
				$this->functionCallStatementFinder = $functionCallStatementFinder;
				$this->cache = $cache;
			}

			public function create(
				ClassReflection $declaringClass,
				\ReflectionMethod $reflection,
				array $phpDocParameterTypes,
				Type $phpDocReturnType = null
			): PhpMethodReflection
			{
				return new PhpMethodReflection(
					$declaringClass,
					$reflection,
					$this->parser,
					$this->functionCallStatementFinder,
					$this->cache,
					$phpDocParameterTypes,
					$phpDocReturnType
				);
			}
		}, new FileTypeMapper($parser, $this->createMock(\Nette\Caching\Cache::class), true));
		$functionReflectionFactory = new class($this->getParser(), $functionCallStatementFinder, $cache) implements FunctionReflectionFactory {
			/** @var \PHPStan\Parser\Parser */
			private $parser;

			/** @var \PHPStan\Parser\FunctionCallStatementFinder */
			private $functionCallStatementFinder;

			/** @var \Nette\Caching\Cache */
			private $cache;

			public function __construct(
				Parser $parser,
				FunctionCallStatementFinder $functionCallStatementFinder,
				\Nette\Caching\Cache $cache
			)
			{
				$this->parser = $parser;
				$this->functionCallStatementFinder = $functionCallStatementFinder;
				$this->cache = $cache;
			}

			public function create(
				\ReflectionFunction $function,
				array $phpDocParameterTypes,
				Type $phpDocReturnType = null
			): FunctionReflection
			{
				return new FunctionReflection(
					$function,
					$this->parser,
					$this->functionCallStatementFinder,
					$this->cache,
					$phpDocParameterTypes,
					$phpDocReturnType
				);
			}
		};
		$broker = new Broker(
			[
				$phpExtension,
				new UniversalObjectCratesClassReflectionExtension([\stdClass::class]),
			],
			[$phpExtension],
			$dynamicMethodReturnTypeExtensions,
			$dynamicStaticMethodReturnTypeExtensions,
			$functionReflectionFactory,
			new FileTypeMapper($this->getParser(), $this->createMock(\Nette\Caching\Cache::class), true)
		);

		return $broker;
	}

	public static function isObsoletePhpParserVersion(): bool
	{
		return !property_exists(\PhpParser\Node\Stmt\Catch_::class, 'types');
	}

}
