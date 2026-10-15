<?php

namespace App\Livewire\Organigramme;

use App\Services\Organigramme\OrganigrammeBuilder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    protected OrganigrammeBuilder $builder;

    public function boot(OrganigrammeBuilder $builder): void
    {
        $this->builder = $builder;
    }

    public function render()
    {
        return view('livewire.organigramme.index', [
            'organigramme' => $this->builder->construire(),
        ]);
    }
}
