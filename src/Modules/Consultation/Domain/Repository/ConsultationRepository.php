<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Repository;

use Src\Modules\Consultation\Domain\Entity\Consultation;

interface ConsultationRepository
{
    public function save(Consultation $consultation): void;
}
