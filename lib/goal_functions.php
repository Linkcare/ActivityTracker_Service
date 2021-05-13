<?php
// Not compliant
const TARGET_STATUS_NC = 1;
// Not accomplished - Reaching 0, 1 times
const TARGET_STATUS_KO1 = 2;
// Not accomplished - Reaching 2, 3 times AND Median6 < GOAL
const TARGET_STATUS_KO2 = 3;
// Accomplished – Reaching 3, 4 times AND Median6 ≥ GOAL
const TARGET_STATUS_OK1 = 4;
// Compliant goal set
const TARGET_STATUS_1ST = 5;
// KO two consecutive weeks
const TARGET_STATUS_KO2BIS = 6;

/**
 * Calculates the target activity for this week based on the past week activity.
 * This function should be invoked by a TASK inserted the first day of the new week.<br>
 * Supported algorithms for calculating the next target:
 * <ul>
 * <li>STEP: used in STEP / PACPAP projects</li>
 * <li>NORTHUMBRIA: used in NORTHUMBRIA project</li>
 * </ul>
 *
 * @param string $algorithm
 * @param string $taskId TASK that invokes the service function
 * @param string $calcDate
 */
function calculateTargetStatus($algorithm, $taskId, $calcDate) {
    $api = LinkcareSoapAPI::getInstance();
    $task = $api->task_get($taskId);
    $admission = $api->admission_get($task->getAdmissionId());

    if (!$calcDate) {
        // Use the TASK date if no other date specified
        $calcDate = $task->getDate();
    }

    // remove time part
    $calcDate = explode(' ', $calcDate)[0];

    $status = null;
    switch ($algorithm) {
        case "NORTHUMBRIA" :
            $status = NORTHUMBRIA_calculate_new_goal($admission, $calcDate);
        default :
            $status = STEP_calculate_new_goal($admission, $calcDate);
    }
    if (!empty($status)) {
        insertTargetStatusTask($admission, $status);
    }
    return ['ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * Inserts the GOAL for the next week.
 * This function should be invoked by a TASK inserted the first day of the new week, and shoud be called after 'calculate_target_status()' because it
 * needs the TASK 'TARGET STATUS'.<br>
 * The $date provided is the date in which the TARGET_STATUS TASK will be inserted. The calculation of the activity is done using the information of
 * the previous week. If no $date is provided, then the date of the TASK will be used.<br>
 *
 *
 * @param string $task TASK that invokes the service function
 * @param int $patientChoice
 * @param string $date
 */
function insertNewGoal($taskId, $patientChoice, $calcDate) {
    $api = LinkcareSoapAPI::getInstance();
    $task = $api->task_get($taskId);
    $admission = $api->admission_get($task->getAdmissionId());

    if (!$calcDate) {
        // Use the TASK date if no other date specified
        $calcDate = $task->getDate();
    }

    // remove time part
    $calcDate = explode(' ', $calcDate)[0];
    $yesterday = date('Y-m-d', strtotime("-1 day", strtotime($calcDate)));

    // Get the last TARGET STATUS to retrieve the possible GOAL choices
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setFromDate($yesterday);
    $filter->setToDate($calcDate);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['TARGET_STATUS']);

    // Get the last PAC_GOALS TASK
    $agrTaskList = $admission->getTaskList(1, 0, $filter, false);
    /* @var APITask $goalTask */
    $agrTask = empty($agrTaskList) ? null : reset($agrTaskList);
    if (!$agrTask) {
        return false;
    }

    /* @var APIForm $agrForm */
    $agrForm = $agrTask->findForm($GLOBALS['FORM_CODES']['TARGET_STATUS']);
    if (!$agrForm) {
        return false;
    }

    $maxGoal = getMaxGoal($admission, $calcDate);

    $goalKeep = 0;
    $goal5m = 0;
    $goal10m = 0;
    if ($q = $agrForm->findQuestion($GLOBALS['ITEM_CODES']['TARGET_GOAL_BASE'])) {
        $goalKeep = intval($q->getValue());
    }
    if (($q = $agrForm->findQuestion($GLOBALS['ITEM_CODES']['TARGET_GOAL_5M'])) && $q->getValue()) {
        $goal5m = intval($q->getValue());
    } else {
        $goal5m = $goalKeep;
    }
    if (($q = $agrForm->findQuestion($GLOBALS['ITEM_CODES']['TARGET_GOAL_10M'])) && $q->getValue()) {
        $goal10m = intval($q->getValue());
    } else {
        $goal10m = $goal5m;
    }

    switch ($patientChoice) {
        case 2 :
            $goalTheor = $goal5m;
            break;
        case 3 :
            $goalTheor = $goal10m;
            break;
        default :
            $goalTheor = $goalKeep;
    }

    insertNewGoalTask($admission, $goalTheor, $maxGoal);
    return ['ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * ALGORITHM FOR PROGRAM: STEP
 * Calculate new goal and status for an admission in a particular date (normally today)
 * The calculations are made with last week data (monday to sunday of previous week of $calcDate)
 * The calculations are made considering a previous goal established in a date before $calcDate
 *
 * @param APIAdmission $admission
 * @param string $calcDate: base date: calculations will be done on the previous week (mon-sun)
 */
function STEP_calculate_new_goal($admission, $calcDate = null) {
    if ($GLOBALS["debugHTML"]) {
        echo ("GOAL CALCULATION ALGORITHM: STEP<br/>");
    }
    if (!$admission) {
        if ($GLOBALS["debugHTML"]) {
            echo ("  ERROR: Admission not provided<br/>");
        }
        return;
    }
    $admissionId = $admission->getId();

    // The calculations will be done with last week data. If today is sunday, then last week is the one that ends today
    $isSunday = (date("N", strtotime($calcDate)) == 7);
    if ($isSunday) {
        $sunday = $calcDate;
    } else {
        $sunday = date('Y-m-d H:i:s', strtotime("last sunday", strtotime($calcDate)));
    }
    $monday = date('Y-m-d', strtotime('last monday', strtotime($sunday)));
    $firstDayInNextPeriod = date('Y-m-d', strtotime("+1 days", strtotime($sunday)));

    $currentGoal = getCurrentGoal($admission, $sunday);
    $maxGoal = 0;

    if ($GLOBALS["debugHTML"]) {
        echo ("CURRENT GOAL for admission $admissionId on date $sunday = $currentGoal<br/>");
    }

    /* Check if the date for which the GOAL is calculated is inside an "agreement period", where the goal will not be increased */
    $inAgreementPeriod = dateIsInAgreementPeriod($admission, $firstDayInNextPeriod);
    if (!$inAgreementPeriod) {
        $maxGoal = getMaxGoal($admission, $calcDate);
    }

    // get steps of today and some calculations relative to the whole week
    $stepStats = getSteps($admission, $monday, $sunday, $currentGoal);
    $weekSteps = $stepStats['sum'];

    $median6 = $stepStats['median6'];
    $validDays = $stepStats['valid_days'];
    $daysReached = $stepStats['reached'];

    $goal5m = 0; // Goal increased in 5 minutes
    $goal10m = 0; // Goal increased in 10 minutes
    $theorGoal = $currentGoal; // Used when the patient did not achieve the current goal

    if ($GLOBALS["debugHTML"]) {
        echo ("calcDate = $calcDate<br/>");
        echo ("Period analyzed = $monday to $sunday<br/><br/>");
        echo ("maximum_goal = $maxGoal<br/>");
        echo ("in_agreement_period = $inAgreementPeriod<br/>");
        echo ("valid_days = $validDays<br/>");
        echo ("median6 = $median6<br/>");
        echo ("days_reached = $daysReached<br/>");
        echo ("<br/>");
    }

    $newStatus = null;
    if ($validDays < 4) {
        // Not enough valid days data. Keep current goal
        if ($GLOBALS["debugHTML"]) {
            echo ("Not enough valid days data. Keep current goal<br/>");
        }
        $theorGoal = $currentGoal;
        $newStatus = TARGET_STATUS_NC;
    }

    // Goal was not established yet. Calculate a new goal for the next week based on patient activity
    if (!$newStatus && !$currentGoal) {
        if ($GLOBALS["debugHTML"]) {
            echo ("Goal was not established yet. Calculate a new goal for the next week based on patient activity<br/>");
        }
        $theorGoal = $median6;
        $newStatus = TARGET_STATUS_1ST;
    }

    // ****** GROUP 1: goal not achieved, and still far
    if (!$newStatus && $daysReached < 2) {
        if ($GLOBALS["debugHTML"]) {
            echo ("GROUP 1: goal not achieved, and still far<br/>");
        }
        $theorGoal = min(($currentGoal > 200 ? $currentGoal - 200 : 1), $median6 + 500);
        $newStatus = TARGET_STATUS_KO1;
    }

    // ****** GROUP 2: goal not achieved, but almost there
    if (!$newStatus && $daysReached < 4 && $median6 < $currentGoal) {
        $lastWeekStatus = getTargetStatus($admission, $sunday); // Check status of the previous week
        if ($GLOBALS["debugHTML"]) {
            echo ("last_week_status: $lastWeekStatus ====> ");
        }
        if ($lastWeekStatus == TARGET_STATUS_KO2) {
            if ($GLOBALS["debugHTML"])
                echo ("GROUP 2: goal not achieved, but almost there - KO Again<br/>");
            $theorGoal = min(($currentGoal > 200 ? $currentGoal - 200 : 1), $median6 + 500);
            $newStatus = TARGET_STATUS_KO2BIS;
        } else {
            if ($GLOBALS["debugHTML"])
                echo ("GROUP 2: goal not achieved, but almost there - KO first time<br/>");
            $theorGoal = $currentGoal;
            $newStatus = TARGET_STATUS_KO2;
        }
    }

    // ****** GROUP 3: goal achieved. Increase goal
    if (!$newStatus) {
        // Calculate options for increasing activity in 5 and 10 minutes
        if ($daysReached < 4) {
            $goal5m = min($median6 + 500, $currentGoal + 500);
            if ($GLOBALS["debugHTML"]) {
                echo ("GROUP 3 (comes from GROUP 2: reached=$daysReached): goal achieved. Calculate 5M increase: $goal5m<br/>");
            }
        } else {
            if ($median6 + 500 < $currentGoal + 1000) {
                $goal5m = min($median6 + 500, $currentGoal + 500);
                if ($GLOBALS["debugHTML"]) {
                    echo ("GROUP 3: goal achieved (low increase). Calculate 5M increase: $goal5m<br/>");
                }
            } else {
                $goal5m = $currentGoal + 500;
                if ($GLOBALS["debugHTML"]) {
                    echo ("GROUP 3: goal achieved (high increase). Calculate 5M increase: $goal5m<br/>");
                }
            }
        }

        $goal10m = $currentGoal + 1000;
        if ($GLOBALS["debugHTML"]) {
            echo ("GROUP 3: goal achieved.. Calculate 10M increase: $goal10m<br/>");
        }

        $newStatus = TARGET_STATUS_OK1;
    }

    // Round to hundreths
    $theorGoal = round($theorGoal, -2);
    if ($inAgreementPeriod) {
        // In an AGREEMENT PERIOD the goal should not be increased
        $goal5m = null;
        $goal10m = null;
    } else {
        $goal5m = round($goal5m, -2);
        $goal10m = round($goal10m, -2);
    }

    $return = ['STATUS' => $newStatus, 'GOAL_BASE' => $theorGoal, 'GOAL_5M' => $goal5m, 'GOAL_10M' => $goal10m, 'MEDIAN6' => $median6,
            'AVG4' => $stepStats['average'], 'VALID_DAYS' => $validDays, 'REACHED' => $daysReached, 'WEEK_STEPS' => $weekSteps,
            'AGREEMENT' => $inAgreementPeriod];

    if ($GLOBALS["debugHTML"]) {
        echo ("Calculate new GOAL for date: $calcDate<br/>");
        echo str_replace('[', '<br/>[', print_r($return, true));
        echo ("<br/><br/>");
    }

    return $return;
}

/**
 * ALGORITHM FOR PROGRAM: NORTHUMBRIA
 * Calculate new goal and status for an admission in a particular date (normally today)
 * The calculations are made with last week data (monday to sunday of previous week of $calcDate)
 * The calculations are made considering a previous goal established in a date before $calcDate
 *
 * @param int $admission
 * @param string $calcDate: base date: calculations will be done on the previous week (mon-sun)
 */
function NORTHUMBRIA_calculate_new_goal($admission, $calcDate, $patient_choice) {
    if ($GLOBALS["debugHTML"]) {
        echo ("GOAL CALCULATION ALGORITHM: NORTHUMBRIA<br/>");
    }
    if (!$admission) {
        if ($GLOBALS["debugHTML"]) {
            echo ("  ERROR: Admission not provided<br/>");
        }
        return;
    }

    // The calculations will be done with last week data. If today is sunday, then last week is the one that ends today
    $isSunday = (date("N", strtotime($calcDate)) == 7);
    if ($isSunday) {
        $sunday = $calcDate;
    } else {
        $sunday = date('Y-m-d H:i:s', strtotime("last sunday", strtotime($calcDate)));
    }
    $monday = date('Y-m-d', strtotime('last monday', strtotime($sunday)));
    $firstDayInNextPeriod = date('Y-m-d', strtotime("+1 days", strtotime($sunday)));

    $currentGoal = getCurrentGoal($admission, $sunday);
    $theorGoal = $currentGoal;

    if ($GLOBALS["debugHTML"]) {
        echo ("CURRENT GOAL for admission $admission on date $sunday = $currentGoal<br/>");
    }

    /* Check if the date for which the GOAL is calculated is inside an "agreement period", where the goal will not be increased */
    $inAgreementPeriod = dateIsInAgreementPeriod($admission, $firstDayInNextPeriod);

    // get steps of today and some calculations relative to the whole week
    $stepStats = getSteps($admission, $monday, $sunday, $currentGoal);
    $weekSteps = $stepStats['sum'];

    // Days with steps over the minimum required to consider that there is activity
    $validDays = array_filter($weekSteps, function ($item) {
        return ($item > 70);
    }, 0);

    // Number of days over the current goal
    $daysReached = array_reduce($validDays,
            function ($carry, $item) use ($currentGoal) {
                if ($currentGoal && $item > $currentGoal) {
                    $carry++;
                }
                return $carry;
            }, 0);

    // mean of the 4 most active days
    $sortedSteps = $validDays;
    rsort($sortedSteps);
    $mean4 = array_average(array_slice($sortedSteps, 0, 4));
    $median = array_median($sortedSteps);

    if ($GLOBALS["debugHTML"]) {
        echo ("calcDate = $calcDate<br/>");
        echo ("Period analyzed = $monday to $sunday<br/><br/>");
        echo ("in_agreement_period = $inAgreementPeriod<br/>");
        echo ("valid_days = " . count($validDays) . "<br/>");
        echo ("mean4 = $mean4<br/>");
        echo ("median = $median<br/>");
        echo ("days_reached = $daysReached<br/>");
        echo ("<br/>");
    }

    $newStatus = null;
    if (count($validDays) < 4) {
        // Not enough valid days data. Keep current goal
        if ($GLOBALS["debugHTML"]) {
            echo ("Not enough valid days data. Keep current goal<br/>");
        }
        $theorGoal = $currentGoal;
        $newStatus = TARGET_STATUS_NC;
    }

    // Goal was not established yet. Calculate a new goal for the next week based on patient activity
    if (!$newStatus && !$currentGoal) {
        if ($GLOBALS["debugHTML"]) {
            echo ("Goal was not established yet. Calculate a new goal for the next week based on patient activity<br/>");
        }
        $theorGoal = $median + 500;
        $newStatus = TARGET_STATUS_1ST;
    }

    if (!$newStatus && $daysReached < 4) {
        if ($GLOBALS["debugHTML"]) {
            echo ("GROUP 1: Goal not achieved. Increase 500 steps based on the median of previous week (or the previous GOAL if lower)<br/>");
        }
        $theorGoal = min($currentGoal, $median + 500);
        $newStatus = TARGET_STATUS_KO1;
    }

    // Goal achieved. Increase goal
    if (!newStatus) {
        // In NORTHUMBRIA the only option is to increase 5M
        $goal5m = $currentGoal + 500;
        if ($GLOBALS["debugHTML"]) {
            echo ("GROUP 2: goal achieved. Calculate 5M increase: $goal5m<br/>");
        }

        $newStatus = TARGET_STATUS_OK1;
    }

    // Round to hundreths
    $theorGoal = round($theorGoal, -2);
    $goal5m = round($theorGoal, -2);

    $return = ['STATUS' => $newStatus, 'GOAL_BASE' => $theorGoal, 'GOAL_5M' => $goal5m, 'MEDIAN6' => $median, 'AVG4' => $mean4,
            'VALID_DAYS' => count($validDays), 'REACHED' => $daysReached, 'WEEK_STEPS' => $weekSteps, 'AGREEMENT' => $inAgreementPeriod];

    if ($GLOBALS["debugHTML"]) {
        echo ("Calculate new GOAL for date: $calcDate<br/>");
        echo str_replace('[', '<br/>[', print_r($return, true));
        echo ("<br/><br/>");
    }

    return $return;
}

/**
 * Find the last TASK with the GOALS and return the value set for the GOAL
 *
 * @param APIAdmission $admission
 * @param string $date
 * @return int
 */
function getCurrentGoal($admission, $date) {
    $currentGoal = 0;

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setToDate($date);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['GOAL']);

    // Get the last PAC_GOALS TASK
    $goalTaskList = $admission->getTaskList(1, 0, $filter, false);
    /* @var APITask $goalTask */
    $goalTask = empty($goalTaskList) ? null : reset($goalTaskList);
    if (!$goalTask) {
        return $currentGoal;
    }

    /* @var APIForm $goalForm */
    $goalForm = $goalTask->findForm($GLOBALS['FORM_CODES']['GOAL']);
    if (!$goalForm) {
        return $currentGoal;
    }

    if ($q = $goalForm->findQuestion($GLOBALS['ITEM_CODES']['GOAL'])) {
        $currentGoal = intval($q->getValue());
    }

    return $currentGoal;
}

/**
 * Find the last TASK with the MAXIMUM GOAL defined for the patient and return the value set for the MAXIMUM GOAL
 *
 * @param APIAdmission $admission
 * @param string $date
 * @return int
 */
function getMaxGoal($admission, $date) {
    $maxGoal = 0;

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setToDate($date);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['MAXGOAL']);

    // Get the last PAC_GOALS TASK
    $goalTaskList = $admission->getTaskList(1, 0, $filter, false);
    /* @var APITask $goalTask */
    $goalTask = empty($goalTaskList) ? null : reset($goalTaskList);
    if (!$goalTask) {
        return $maxGoal;
    }

    /* @var APIForm $goalForm */
    $goalForm = $goalTask->findForm($GLOBALS['FORM_CODES']['MAXGOAL']);
    if (!$goalForm) {
        return $maxGoal;
    }

    if ($q = $goalForm->findQuestion($GLOBALS['ITEM_CODES']['MAXGOAL'])) {
        $maxGoal = intval($q->getValue());
    }

    return $maxGoal;
}

/**
 * Find the STATUS of a patient in the first TARGET_STATUS task before a specific date
 *
 * @param APIAdmission $admission
 * @param string $date
 * @return int
 */
function getTargetStatus($admission, $date) {
    $status = 0;

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setToDate($date);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['TARGET_STATUS']);

    // Get the last PAC_GOALS TASK
    $taskList = $admission->getTaskList(1, 0, $filter, false);
    /* @var APITask $statusTask */
    $statusTask = empty($taskList) ? null : reset($taskList);
    if (!$statusTask) {
        return $status;
    }

    /* @var APIForm $goalForm */
    $statusForm = $statusTask->findForm($GLOBALS['FORM_CODES']['TARGET_STATUS']);
    if (!$statusForm) {
        return $status;
    }

    if ($q = $statusForm->findQuestion($GLOBALS['ITEM_CODES']['TARGET_STATUS'])) {
        $status = $q->getValue();
    }

    return $status;
}

/**
 * Returns true if the date provided is inside an AGREEMENT PERIOD
 *
 * @param APIAdmission $admission
 * @param string $date
 * @return boolean
 */
function dateIsInAgreementPeriod($admission, $date) {
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setToDate($date);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['AGREEMENT']);

    // Get the last PAC_GOALS TASK
    $agrTaskList = $admission->getTaskList(1, 0, $filter, false);
    /* @var APITask $goalTask */
    $agrTask = empty($agrTaskList) ? null : reset($agrTaskList);
    if (!$agrTask) {
        return false;
    }

    /* @var APIForm $agrForm */
    $agrForm = $agrTask->findForm($GLOBALS['FORM_CODES']['AGREEMENT']);
    if (!$agrForm) {
        return false;
    }

    if ($q = $agrForm->findQuestion($GLOBALS['ITEM_CODES']['AGREEMENT_START'])) {
        $startDate = $q->getValue();
    }
    if ($q = $agrForm->findQuestion($GLOBALS['ITEM_CODES']['AGREEMENT_END'])) {
        $endDate = $q->getValue();
    }

    if ($date >= $startDate && $date <= $endDate) {
        // The date provided is inside an agreement period
        return true;
    }

    return false;
}

/**
 *
 * @param APIAdmission $admission
 * @param string $fromDate
 * @param string $toDate
 * @param int $goal
 * @return number[]|NULL[]|unknown[]|unknown[][]
 */
function getSteps($admission, $fromDate, $toDate, $goal = null) {
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setFromDate($fromDate);
    $filter->setToDate($toDate);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['STEPS']);

    $taskList = $admission->getTaskList(100, 0, $filter, false);
    $i = 0;
    $sum = 0;
    $values = [];
    $max_four = [];
    $steps_week = [];
    foreach ($taskList as $t) {
        $f = $t->findForm($GLOBALS['FORM_CODES']['STEPS']);
        if ($f && ($q = $f->findQuestion($GLOBALS['ITEM_CODES']['STEPS']))) {
            $date = $t->getDate();
            $steps = intval($q->getValue());
            $values[] = $steps;
            $steps_week[$date] = $steps;
            if ($steps >= 70) {
                $valid_values[] = $steps;
                $sum = $sum + $steps;
                $i++;
            }
        }
    }

    $all_steps = array_sum($values);
    if ($goal) {
        // how many times reached goal:
        $reached_goal_count = count(array_filter($values, function ($x) use ($goal) {
            return $x >= $goal;
        }));
    }
    $values_original = $values;
    $j = 0;
    $sum_max_four = 0;
    while ($j < 4) {
        if (count($values) == 0)
            break;
        $key = array_keys($values, max($values));
        $key = $key[0];
        $max_four[] = max($values);
        $sum_max_four = $sum_max_four + max($values);
        unset($values[$key]);
        $j++;
    }

    if (isset($valid_values) && (count($valid_values) > 0)) {
        rsort($valid_values, SORT_NUMERIC);
        $average6 = (float) array_sum(array_slice($valid_values, 0, 6));
        $average6 = round($average6 / (min(count($valid_values), 6)));
    } else {
        $average6 = 0;
    }

    rsort($values_original, SORT_NUMERIC);
    $median4 = round(array_median(array_slice($values_original, 0, 4)));
    $median6 = round(array_median(array_slice($values_original, 0, 6)));

    $average4 = 0;
    $average = 0;
    if (isset($max_four) && count($max_four)) {
        $average4 = round(array_sum($max_four) / count($max_four)); // mean 4
    }
    if (isset($valid_values) && count($valid_values)) {
        $average = round(array_sum($valid_values) / count($valid_values)); // mean
    }

    $return = ["sum" => $sum, "median6" => $median6, "reached" => intval($reached_goal_count), "all_steps" => $all_steps, "median" => $median4,
            "average" => $average, "average4" => $average4, "average6" => $average6, "valid_days" => $i, "steps" => $steps_week];
    return $return;
}

/**
 * Creates a new TASK "TARGET_STATUS"
 *
 * @param APIAdmission $admission
 * @param int[] $status
 */
function insertTargetStatusTask($admission, $status) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admission->getId(), $GLOBALS["TASK_CODES"]["TARGET_STATUS"]);
    $task = $api->task_get($taskId);
    $targetForm = $task->findForm($GLOBALS["FORM_CODES"]["TARGET_STATUS"]);

    $arrQuestions = [];
    if ($targetForm) {

        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_STATUS"])) {
            $q->setValue($status['STATUS']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_GLOBAL_PERFORMANCE"])) {
            $q->setValue(1);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_NUM_DAYS_ACCOM"])) {
            $q->setValue($status['REACHED']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_MEDIAN6"])) {
            $q->setValue($status['MEDIAN6']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_AVG4"])) {
            $q->setValue($status['AVG4']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_WEEK_STEPS"])) {
            $q->setValue($status['WEEK_STEPS']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_GOAL_BASE"])) {
            $q->setValue($status['GOAL_BASE']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_GOAL_5M"])) {
            $q->setValue($status['GOAL_5M']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_GOAL_10M"])) {
            $q->setValue($status['GOAL_10M']);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["TARGET_IN_AGREEMENT"])) {
            $q->setValue($status['AGREEMENT'] ? 1 : 2);
            $arrQuestions[] = $q;
        }

        $api->form_set_all_answers($targetForm->getId(), $arrQuestions, true);
    } else {
        throw new APIException("FORM NOT FOUND", "FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["TARGET_STATUS"] . ")");
    }

    // $task->clearAssignments();
    // $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    // $task->addAssignments($a);
    // $api->task_set($task);

    return $taskId;
}

/**
 * Creates a new TASK "GOALS" with the goal for the patient in the next week
 *
 * @param APIAdmission $admission
 * @param int $theorGoal Goal that should be assigned to the patient without checking any limitation
 * @param int $maxGoal Maximum goal defined for the patient. If the new goal exceeds the maximum, then it will be limited to the maximum
 */
function insertNewGoalTask($admission, $theorGoal, $maxGoal) {
    $limitExceeded = false;
    if ($maxGoal) {
        $limitExceeded = ($theorGoal >= $maxGoal);
        $newGoal = min($theorGoal, $maxGoal);
    } else {
        $newGoal = $theorGoal;
    }

    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admission->getId(), $GLOBALS["TASK_CODES"]["GOAL"]);
    $task = $api->task_get($taskId);
    $targetForm = $task->findForm($GLOBALS["FORM_CODES"]["GOAL"]);

    $arrQuestions = [];
    if ($targetForm) {

        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["GOAL"])) {
            $q->setValue($newGoal);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["ITEM_CODES"]["THEOR_GOAL"])) {
            $q->setValue($theorGoal);
            $arrQuestions[] = $q;
        }

        $api->form_set_all_answers($targetForm->getId(), $arrQuestions, true);
    } else {
        throw new APIException("FORM NOT FOUND", "FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["TARGET_STATUS"] . ")");
    }

    // $task->clearAssignments();
    // $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    // $task->addAssignments($a);
    // $api->task_set($task);

    if ($limitExceeded) {
        // Insert a new EVENT indicating that the maximum GOAL has been exceeded
    }

    return $taskId;
}