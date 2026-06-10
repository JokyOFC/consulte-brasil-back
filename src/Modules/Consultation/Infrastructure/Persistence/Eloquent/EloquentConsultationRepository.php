<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent;

use Src\Modules\Consultation\Domain\Entity\Consultation;
use Src\Modules\Consultation\Domain\Repository\ConsultationRepository;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;

final class EloquentConsultationRepository implements ConsultationRepository
{
    public function save(Consultation $consultation): void
    {
        $model = ConsultationModel::find($consultation->id) ?? new ConsultationModel;

        $model->id = $consultation->id;
        $model->account_id = $consultation->accountId;
        $model->api_key_id = $consultation->apiKeyId;
        $model->query_type = $consultation->queryType;
        $model->provider_id = $consultation->providerId;
        $model->status = $consultation->status;
        $model->credit_cost = $consultation->creditCost;
        $model->reservation_id = $consultation->reservationId;
        $model->request_hash = $consultation->requestHash;
        $model->latency_ms = $consultation->latencyMs;
        $model->http_status = $consultation->httpStatus;
        $model->created_at = $consultation->createdAt;
        $model->save();
    }
}
