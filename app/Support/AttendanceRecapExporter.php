<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceRecapExporter
{
    private const PROOF_PHOTO_COLUMN = 'L';

    private const PROOF_PHOTO_MAX_WIDTH = 96;

    private const PROOF_PHOTO_MAX_HEIGHT = 72;

    /**
     * Build an XLSX workbook and return its binary contents.
     */
    public function build(?AttendanceFilter $filter = null, ?User $actor = null): string
    {
        $filter ??= new AttendanceFilter;
        $imageResources = [];
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $detailSheet = $spreadsheet->getActiveSheet();
        $detailSheet->setTitle('Rekap Absensi');
        $this->writeDetailSheet($detailSheet, $filter, $actor, $imageResources);

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Total Kehadiran');
        $summarySheet->fromArray($this->summaryRows($filter, $actor), null, 'A1', true);
        $this->formatSheet($summarySheet);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                if ($column !== self::PROOF_PHOTO_COLUMN) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'attendance-recap-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $contents = file_get_contents($path);
        unlink($path);

        foreach ($imageResources as $imageResource) {
            imagedestroy($imageResource);
        }

        return $contents === false ? '' : $contents;
    }

    /**
     * @return Builder<User>
     */
    public function employeeSummaryQuery(?AttendanceFilter $filter = null, ?User $actor = null): Builder
    {
        $filter ??= new AttendanceFilter;
        $countForStatus = fn (AttendanceStatus $status) => function (Builder $query) use ($filter, $actor, $status): void {
            $filter->apply($query, $actor);
            $query->where('status', $status->value);
        };
        $filteredAttendances = function (Builder $query) use ($filter, $actor): void {
            $filter->apply($query, $actor);
        };

        $query = User::query()
            ->where('role', UserRole::Employee->value)
            ->withCount([
                'attendances as on_time_count' => $countForStatus(AttendanceStatus::OnTime),
                'attendances as late_count' => $countForStatus(AttendanceStatus::Late),
                'attendances as sick_count' => $countForStatus(AttendanceStatus::Sick),
                'attendances as leave_count' => $countForStatus(AttendanceStatus::Leave),
                'attendances as permit_count' => $countForStatus(AttendanceStatus::Permit),
                'attendances as absent_count' => $countForStatus(AttendanceStatus::Absent),
            ])
            ->withMin(['attendances as first_attendance_date' => $filteredAttendances], 'date')
            ->withMax(['attendances as latest_attendance_date' => $filteredAttendances], 'date')
            ->orderBy('name');

        if ($filter->hasConstraints()) {
            $query->whereHas('attendances', $filteredAttendances);
        }

        return $query;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function detailHeaders(): array
    {
        return [
            'Nama Karyawan',
            'Username',
            'Email',
            'Tanggal',
            'Status',
            'Kantor',
            'Waktu Masuk',
            'Waktu Keluar',
            'Durasi Jam',
            'Sumber',
            'Ada Foto',
            'Foto Bukti',
            'Tipe Pengajuan',
            'Keterangan Pengajuan',
            'Catatan Review',
            'Reviewer',
            'Dibuat Oleh',
            'Diubah Oleh',
        ];
    }

    /**
     * @param  array<int, GdImage>  $imageResources
     */
    private function writeDetailSheet(Worksheet $sheet, AttendanceFilter $filter, ?User $actor, array &$imageResources): void
    {
        $sheet->fromArray($this->detailHeaders(), null, 'A1', true);
        $query = Attendance::with(['user', 'office', 'attendanceRequest.reviewer'])
            ->orderBy('date')
            ->orderBy('user_id');

        $filter->apply($query, $actor);

        $rowNumber = 2;
        $query->each(function (Attendance $attendance) use ($sheet, &$imageResources, &$rowNumber): void {
            $request = $attendance->attendanceRequest;
            $thumbnail = $this->createProofThumbnail($attendance->proof_photo);
            $hasPhoto = $thumbnail !== null;

            $sheet->fromArray([[
                $attendance->user?->name,
                $attendance->user?->username,
                $attendance->user?->email,
                $attendance->date?->format('Y-m-d'),
                $this->statusLabel($attendance->status),
                $attendance->office?->name,
                $attendance->in_at?->format('Y-m-d H:i:s'),
                $attendance->out_at?->format('Y-m-d H:i:s'),
                $this->durationHours($attendance),
                $this->sourceLabel($attendance),
                $hasPhoto ? 'Ya' : 'Tidak',
                null,
                $request ? $this->statusLabel($request->type) : null,
                $request?->description,
                $request?->rejection_reason,
                $request?->reviewer?->username,
                $attendance->created_by,
                $attendance->updated_by,
            ]], null, 'A'.$rowNumber, true);

            if ($thumbnail !== null) {
                $imageResources[] = $thumbnail['image'];
                $this->addProofDrawing($sheet, $thumbnail, $rowNumber);
                $sheet->getRowDimension($rowNumber)->setRowHeight(58);
            }

            $rowNumber++;
        });

        $this->formatSheet($sheet);
        $sheet->getColumnDimension(self::PROOF_PHOTO_COLUMN)->setWidth(18);
        $sheet->getStyle('N:N')->getAlignment()->setWrapText(true);
        $sheet->getStyle('O:O')->getAlignment()->setWrapText(true);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function summaryRows(AttendanceFilter $filter, ?User $actor): array
    {
        $rows = [[
            'Nama Karyawan',
            'Username',
            'Email',
            'Tepat Waktu',
            'Terlambat',
            'Total Kehadiran',
            'Sakit',
            'Cuti',
            'Izin',
            'Tidak Hadir',
            'Tanggal Pertama',
            'Tanggal Terakhir',
        ]];

        $this->employeeSummaryQuery($filter, $actor)
            ->each(function (User $employee) use (&$rows): void {
                $rows[] = [
                    $employee->name,
                    $employee->username,
                    $employee->email,
                    $employee->on_time_count,
                    $employee->late_count,
                    $employee->on_time_count + $employee->late_count,
                    $employee->sick_count,
                    $employee->leave_count,
                    $employee->permit_count,
                    $employee->absent_count,
                    $employee->first_attendance_date,
                    $employee->latest_attendance_date,
                ];
            });

        return $rows;
    }

    private function durationHours(Attendance $attendance): ?float
    {
        if (! $attendance->in_at || ! $attendance->out_at) {
            return null;
        }

        return round($attendance->in_at->diffInMinutes($attendance->out_at) / 60, 2);
    }

    private function sourceLabel(Attendance $attendance): string
    {
        if ($attendance->attendance_request_id !== null) {
            return 'Pengajuan';
        }

        if (in_array($attendance->status, [AttendanceStatus::Sick, AttendanceStatus::Leave, AttendanceStatus::Permit], true)) {
            return 'Input Manual';
        }

        return 'Absensi';
    }

    private function statusLabel(?AttendanceStatus $status): ?string
    {
        return match ($status) {
            AttendanceStatus::OnTime => 'Tepat Waktu',
            AttendanceStatus::Late => 'Terlambat',
            AttendanceStatus::Absent => 'Tidak Hadir',
            AttendanceStatus::Sick => 'Sakit',
            AttendanceStatus::Leave => 'Cuti',
            AttendanceStatus::Permit => 'Izin',
            null => null,
        };
    }

    private function formatSheet(Worksheet $sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = max(1, $sheet->getHighestRow());

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
        $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'F3F0EA',
                ],
            ],
        ]);
        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * @return array{image: GdImage, width: int, height: int}|null
     */
    private function createProofThumbnail(?string $proofPhoto): ?array
    {
        if (! $proofPhoto || ! preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/', $proofPhoto, $matches)) {
            return null;
        }

        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $scale = min(self::PROOF_PHOTO_MAX_WIDTH / $sourceWidth, self::PROOF_PHOTO_MAX_HEIGHT / $sourceHeight, 1);
        $width = max(1, (int) floor($sourceWidth * $scale));
        $height = max(1, (int) floor($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        return [
            'image' => $thumbnail,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param  array{image: GdImage, width: int, height: int}  $thumbnail
     */
    private function addProofDrawing(Worksheet $sheet, array $thumbnail, int $rowNumber): void
    {
        $drawing = new MemoryDrawing;
        $drawing->setName('Foto Bukti');
        $drawing->setDescription('Foto Bukti');
        $drawing->setImageResource($thumbnail['image']);
        $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
        $drawing->setWidthAndHeight($thumbnail['width'], $thumbnail['height']);
        $drawing->setOffsetX(6);
        $drawing->setOffsetY(4);
        $drawing->setCoordinates(self::PROOF_PHOTO_COLUMN.$rowNumber);
        $drawing->setWorksheet($sheet);
    }
}
