<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\FilterAttendanceRequest;
use App\Support\AttendanceRecapExporter;
use App\Support\AttendanceReportBuilder;
use App\Support\Pagination\PaginatesCollections;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AttendanceReportController extends Controller
{
    use ApiResponse;

    public function summary(FilterAttendanceRequest $request, AttendanceReportBuilder $reportBuilder): JsonResponse
    {
        $paginator = PaginatesCollections::paginate(
            $reportBuilder->summaryRows($request->toFilter(), $request->user()),
            $request->pagination(),
            $request,
        );

        return $this->paginatedSuccess(
            'Attendance summary retrieved successfully.',
            $paginator->getCollection(),
            $paginator,
        );
    }

    public function chart(FilterAttendanceRequest $request, AttendanceReportBuilder $reportBuilder): JsonResponse
    {
        return $this->success(
            'Attendance chart retrieved successfully.',
            $reportBuilder->chartBuckets($request->toFilter(), $request->user()),
        );
    }

    public function export(FilterAttendanceRequest $request, AttendanceRecapExporter $exporter): Response
    {
        $filename = 'attendance-recap-'.now()->format('Ymd-His').'.xlsx';

        return response($exporter->build($request->toFilter(), $request->user()), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
