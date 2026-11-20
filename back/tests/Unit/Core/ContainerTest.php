<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use LogicException;
use Tests\TestCase;

final class Clock
{
    public function __construct(public readonly string $zone = 'UTC')
    {
    }
}

final class Agenda
{
    public function __construct(public readonly Clock $clock)
    {
    }
}

final class Planner
{
    public function __construct(public readonly Agenda $agenda, public readonly Clock $clock)
    {
    }
}

final class NeedsScalar
{
    public function __construct(public readonly string $name)
    {
    }
}

final class Loop
{
    public function __construct(public readonly LoopBack $back)
    {
    }
}

final class LoopBack
{
    public function __construct(public readonly Loop $loop)
    {
    }
}

interface Greeter
{
}

final class ContainerTest extends TestCase
{
    public function testAutowiresConstructorDependenciesAsSharedInstances(): void
    {
        $container = new Container();
        $planner = $container->get(Planner::class);

        $this->assertSame('UTC', $planner->clock->zone, 'scalar default used');
        $this->assertSame($planner->clock, $planner->agenda->clock, 'one instance per container');
        $this->assertSame($planner, $container->get(Planner::class));
        $this->assertNotSame($planner, (new Container())->get(Planner::class));
    }

    public function testFactoryOverridesAutowiring(): void
    {
        $container = new Container();
        $container->set(Clock::class, static fn (): Clock => new Clock('Europe/Paris'));

        $this->assertSame('Europe/Paris', $container->get(Agenda::class)->clock->zone);
    }

    public function testUnresolvableDependenciesFailLoudly(): void
    {
        $container = new Container();
        $this->assertThrows(LogicException::class, fn () => $container->get(NeedsScalar::class));
        $this->assertThrows(LogicException::class, fn () => $container->get(Greeter::class));
        $cycle = $this->assertThrows(LogicException::class, fn () => $container->get(Loop::class));
        $this->assertStringContains('Circular dependency', $cycle->getMessage());

        $container->set(Clock::class, static fn (): Agenda => new Agenda(new Clock()));
        $this->assertThrows(LogicException::class, fn () => $container->get(Clock::class));
    }
}
