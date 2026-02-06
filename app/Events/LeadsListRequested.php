<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class LeadsListRequested
{
    use Dispatchable;

    public Request $request;
    public $userId;
    public $userRole;

    public function __construct(Request $request, $userId, $userRole)
    {
        $this->request = $request;
        $this->userId = $userId;
        $this->userRole = $userRole;
    }
}
