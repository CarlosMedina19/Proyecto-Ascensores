<?php

namespace App\Http\Requests\Plan;

use App\Models\PlanMantenimiento;

class ActualizarPlanRequest extends GuardarPlanRequest
{
    protected function planActual(): ?PlanMantenimiento
    {
        return $this->route('plan');
    }
}
