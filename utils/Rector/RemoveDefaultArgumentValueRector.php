<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Core\Rector\AbstractRector;
use Rector\DeadCode\NodeManipulator\CallDefaultParamValuesResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Originally part of rector/rector dead-code set that removed @see https://github.com/rectorphp/rector/pull/6148
 */
final class RemoveDefaultArgumentValueRector extends AbstractRector
{
	/**
	 * @var CallDefaultParamValuesResolver
	 */
	private $callDefaultParamValuesResolver;

	/**
	 * @var ReflectionProvider
	 */
	private $reflectionProvider;

	public function __construct(
		CallDefaultParamValuesResolver $callDefaultParamValuesResolver,
		ReflectionProvider $reflectionProvider
	)
	{
		$this->callDefaultParamValuesResolver = $callDefaultParamValuesResolver;
		$this->reflectionProvider             = $reflectionProvider;
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition('Remove argument value, if it is the same as default value', [
			new CodeSample(
				<<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        $this->runWithDefault([]);
        $card = self::runWithStaticDefault([]);
    }

    public function runWithDefault($items = [])
    {
        return $items;
    }

    public function runStaticWithDefault($cards = [])
    {
        return $cards;
    }
}
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        $this->runWithDefault();
        $card = self::runWithStaticDefault();
    }

    public function runWithDefault($items = [])
    {
        return $items;
    }

    public function runStaticWithDefault($cards = [])
    {
        return $cards;
    }
}
CODE_SAMPLE
				),
		]);
	}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [
			MethodCall::class,
			StaticCall::class,
			FuncCall::class,
		];
	}

	/**
	 * @param MethodCall|StaticCall|FuncCall $node
	 */
	public function refactor(Node $node): ?Node
	{
		if ($this->shouldSkip($node))
		{
			return null;
		}

		$defaultValues = $this->callDefaultParamValuesResolver->resolveFromCall($node);

		$keysToRemove = $this->resolveKeysToRemove($node, $defaultValues);
		if ($keysToRemove === [])
		{
			return null;
		}

		foreach ($keysToRemove as $keyToRemove)
		{
			if (! isset($defaultValues[$keyToRemove]))
			{
				continue;
			}

			$this->nodeRemover->removeArg($node, $keyToRemove);
		}

		return $node;
	}

	/**
	 * @param MethodCall|StaticCall|FuncCall $node
	 */
	private function shouldSkip(Node $node): bool
	{
		if ($node->args === [])
		{
			return true;
		}

		if (! $node instanceof FuncCall)
		{
			return false;
		}

		if (! $node->name instanceof Name)
		{
			return true;
		}

		$functionName = $this->getName($node);
		if ($functionName === null)
		{
			return false;
		}

		$name = new Name($functionName);
		if (! $this->reflectionProvider->hasFunction($name, null))
		{
			return false;
		}

		$reflectionFunction = $this->reflectionProvider->getFunction($name, null);

		// skip native functions, hard to analyze without stubs (stubs would make working with IDE non-practical)
		return $reflectionFunction->isBuiltin();
	}

	/**
	 * @param  StaticCall|MethodCall|FuncCall $node
	 * @param  Expr[]|mixed[]                 $defaultValues
	 * @return int[]
	 */
	private function resolveKeysToRemove(Node $node, array $defaultValues): array
	{
		$keysToKeep = [];

		/**
		 * @var int $key
		 */
		foreach ($node->args as $key => $arg)
		{
			if (! isset($defaultValues[$key]))
			{
				$keysToKeep[] = $key;
				continue;
			}

			if (! $this->nodeComparator->areNodesEqual($defaultValues[$key], $arg->value))
			{
				$keysToKeep[] = $key;
			}
		}

		$lastKeyToKeep = end($keysToKeep);
		$maxKey        = count($node->args) - 1;

		if ($lastKeyToKeep === false)
		{
			return range(0, $maxKey);
		}

		$startremove = $lastKeyToKeep + 1;
		if ($maxKey < $startremove)
		{
			return [];
		}

		return range($startremove, $maxKey);
	}
}