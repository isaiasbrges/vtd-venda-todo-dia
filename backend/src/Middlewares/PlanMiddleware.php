<?php
declare(strict_types=1);

namespace VTD\Middlewares;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Security\SessionManager;
use VTD\Database\Connection;

class PlanMiddleware
{
    public function checkAccess(?string $plan): bool
    {
        return $plan === 'plus';
    }

    public function handle(Request $request): array
    {
        $authMiddleware = new AuthMiddleware();
        $account = $authMiddleware->handle($request);

        $plan = $account['store_plan'] ?? 'essential';
        if (!$this->checkAccess($plan)) {
            Response::error('PLAN_UPGRADE_REQUIRED', 'O módulo de Controle Financeiro faz parte do Kit + Financeiro.', 403, [
                'current_plan'  => $plan,
                'required_plan' => 'plus'
            ]);
        }

        return $account;
    }
}
