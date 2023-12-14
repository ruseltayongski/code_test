<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * Get bookings based on user or admin/superadmin role.
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = $request->__authenticatedUser;

        if ($user->user_type == config('app.admin_role_id') || $user->user_type == config('app.superadmin_role_id')) {
            $response = $this->repository->getAll($request);
        } elseif ($userId = $request->get('user_id')) {
            $response = $this->repository->getUsersJobs($userId);
        }

        return response($response);
    }

    /**
     * Show details of a specific job.
     *
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->getJobDetails($id);

        return response($job);
    }

    /**
     * Store a new job.
     *
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->storeJob($request->__authenticatedUser, $data);

        return response($response);
    }

    /**
     * Update an existing job.
     *
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $authenticatedUser);

        return response($response);
    }

    /**
     * Store an immediate job email.
     *
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * Get job history for a user.
     *
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $userId = $request->get('user_id');

        if ($userId) {
            $response = $this->repository->getUsersJobsHistory($userId, $request);
            return response($response);
        }

        return response([]);
    }

    public function acceptJob(Request $request)
    {
        $data = $this->validateJobRequest($request);
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($jobId, $user);

        return response($response);
    }

    public function cancelJob(Request $request)
    {
        $data = $this->validateJobRequest($request);
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    public function endJob(Request $request)
    {
        $data = $this->validateJobRequest($request);

        $response = $this->repository->endJob($data);

        return response($response);
    }

    public function customerNotCall(Request $request)
    {
        $data = $this->validateJobRequest($request);

        $response = $this->repository->customerNotCall($data);

        return response($response);
    }

    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $this->validateDistanceFeedRequest($request);

        $this->updateDistance($data);
        $this->updateJobDetails($data);

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $this->validateJobRequest($request);
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $this->validateJobRequest($request);
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    public function resendSMSNotifications(Request $request)
    {
        $data = $this->validateJobRequest($request);
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

    protected function validateJobRequest(Request $request)
    {
        return $request->validate([
            // Define validation rules for job-related requests
        ]);
    }

    protected function validateDistanceFeedRequest(Request $request)
    {
        return $request->validate([
            // Define validation rules for distance feed requests
        ]);
    }

    protected function updateDistance(array $data)
    {
        $jobId = $data['jobid'];

        if (isset($data['distance']) && $data['distance'] != "") {
            $distance = $data['distance'];
        } else {
            $distance = "";
        }

        if (isset($data['time']) && $data['time'] != "") {
            $time = $data['time'];
        } else {
            $time = "";
        }

        if ($time || $distance) {
            Distance::where('job_id', '=', $jobId)->update(['distance' => $distance, 'time' => $time]);
        }
    }

    protected function updateJobDetails(array $data)
    {
        $jobId = $data['jobid'];

        if (isset($data['session_time']) && $data['session_time'] != "") {
            $session = $data['session_time'];
        } else {
            $session = "";
        }

        if ($data['flagged'] == 'true') {
            if ($data['admincomment'] == '') {
                return "Please, add comment";
            }
            $flagged = 'yes';
        } else {
            $flagged = 'no';
        }

        // Add other conditions and updates for job details as needed

        Job::where('id', '=', $jobId)->update([
            'session_time' => $session,
            'flagged' => $flagged,
            // Add other fields and updates as needed
        ]);
    }

}
