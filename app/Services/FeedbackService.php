<?php

namespace App\Services;

use App\Enums\FeedbackStatus;
use App\Models\FeedbackReply;
use App\Models\FeedbackTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeedbackService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Kirim masukan atau komplain baru; nomor tiket dibuat otomatis.
     *
     * @param  array{type: string, category: string, subject: string, body: string, is_anonymous?: bool}  $data
     */
    public function submit(User $user, array $data, ?UploadedFile $attachment = null): FeedbackTicket
    {
        return DB::transaction(function () use ($user, $data, $attachment): FeedbackTicket {
            $ticket = FeedbackTicket::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'type' => $data['type'],
                'category' => $data['category'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'attachment_path' => $attachment?->store('feedback-attachments', 'local'),
                'status' => FeedbackStatus::New,
            ]);

            $ticket->update(['number' => sprintf('MK-%d-%04d', now()->year, $ticket->id)]);

            return $ticket;
        });
    }

    /**
     * Tambah balasan. Balasan penangani memulai proses; balasan pengirim membuka kembali tiket yang selesai.
     */
    public function reply(FeedbackTicket $ticket, User $author, string $body): FeedbackReply
    {
        $isHandler = FeedbackTicket::isHandler($author);

        if (! $isHandler && ! $ticket->isOwnedBy($author)) {
            throw ValidationException::withMessages(['body' => 'Kamu tidak bisa membalas tiket ini.']);
        }

        if (! $ticket->isOpen()) {
            throw ValidationException::withMessages(['body' => 'Tiket sudah ditutup dan tidak menerima balasan.']);
        }

        return DB::transaction(function () use ($ticket, $author, $body, $isHandler): FeedbackReply {
            $reply = $ticket->replies()->create(['user_id' => $author->id, 'body' => $body]);

            if ($isHandler && $ticket->status === FeedbackStatus::New) {
                $ticket->update(['status' => FeedbackStatus::InProgress, 'handled_by' => $author->id]);
            } elseif (! $isHandler && $ticket->status === FeedbackStatus::Resolved) {
                $ticket->update(['status' => FeedbackStatus::InProgress, 'resolved_at' => null]);
            }

            return $reply;
        });
    }

    /**
     * Penangani mengubah status tiket dan menjadi penanggung jawab bila belum ada.
     */
    public function changeStatus(FeedbackTicket $ticket, User $handler, FeedbackStatus $status): FeedbackTicket
    {
        if (! FeedbackTicket::isHandler($handler)) {
            throw ValidationException::withMessages(['status' => 'Hanya admin atau pimpinan yang bisa mengubah status.']);
        }

        $before = $ticket->status;

        $ticket->update([
            'status' => $status,
            'handled_by' => $ticket->handled_by ?? $handler->id,
            'resolved_at' => in_array($status, [FeedbackStatus::Resolved, FeedbackStatus::Closed], true) ? ($ticket->resolved_at ?? now()) : null,
        ]);

        if ($before !== $status) {
            $this->audit->log('feedback.status_changed', $ticket, ['status' => $before->value], ['status' => $status->value]);
        }

        return $ticket;
    }
}
