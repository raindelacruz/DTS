<?php

/** Read-only responsibility projection; does not change assignment or slip status. */
class ActionSlipPendingAction
{
    public static function names(array $slip, array $assignments, array $users, array $departments, bool $firstNamesOnly = false): array
    {
        if (($slip['status'] ?? '') === 'Cancelled') {
            return [];
        }

        $people = [];
        $offices = [];
        foreach ($users as $user) {
            $people[(int) $user['id']] = $user;
        }
        foreach ($departments as $office) {
            $offices[(int) $office['id']] = $office;
        }
        $names = [];
        $addPerson = static function ($userId) use (&$names, $people, $firstNamesOnly) {
            $user = $people[(int) $userId] ?? null;
            if (!$user || ($user['status'] ?? '') !== 'active') {
                $names['unavailable'] = 'Responsible person unavailable';
                return;
            }
            $middle = rtrim(trim((string) ($user['middle_initial'] ?? '')), '.');
            $name = trim(($user['firstname'] ?? '') . ' ' . ($middle !== '' ? $middle . '. ' : '') . ($user['lastname'] ?? ''));
            if ($firstNamesOnly) {
                $name = trim((string) ($user['firstname'] ?? ''));
            }
            $names[(int) $userId] = $name !== '' ? $name : 'Responsible person unavailable';
        };
        $addManagers = static function ($officeId) use ($people, $addPerson, &$names) {
            $found = false;
            foreach ($people as $person) {
                if ((int) $person['department_id'] === (int) $officeId
                    && $person['role'] === 'manager' && $person['status'] === 'active') {
                    $addPerson($person['id']);
                    $found = true;
                }
            }
            if (!$found) {
                $names['unavailable'] = 'Responsible person unavailable';
            }
        };
        $addAssignmentManager = static function ($assignment) use ($addPerson, $addManagers, $people, &$names) {
            if (!empty($assignment['user_id'])) {
                $person = $people[(int) $assignment['user_id']] ?? null;
                if (!$person || $person['role'] !== 'manager'
                    || (int) $person['department_id'] !== (int) $assignment['department_id']) {
                    $names['unavailable'] = 'Responsible person unavailable';
                } else {
                    $addPerson($assignment['user_id']);
                }
            } else {
                $addManagers($assignment['department_id']);
            }
        };

        // Old slips may predate assignment rows. Use their recorded current recipient.
        if (!$assignments && !in_array($slip['status'] ?? '', ['Completed', 'Cancelled'], true)) {
            $officeId = (int) (($slip['current_unit_id'] ?? 0) ?: ($slip['current_division_id'] ?? 0)
                ?: ($slip['current_department_id'] ?? 0) ?: ($slip['receiving_department_id'] ?? 0));
            $assignments[] = [
                'assignment_type' => !empty($slip['assigned_staff_id']) ? 'staff' : ($offices[$officeId]['office_level'] ?? 'department'),
                'department_id' => $officeId,
                'user_id' => $slip['assigned_staff_id'] ?? null,
                'status' => $slip['status'] ?? 'Released',
            ];
        }
        $assignments = array_values(array_filter($assignments, static function ($assignment) {
            return ($assignment['status'] ?? '') !== 'Cancelled';
        }));

        // A completed submission creates a review action, even if the overall badge is Completed.
        foreach ($assignments as $assignment) {
            if ($assignment['status'] !== 'Completed') {
                continue;
            }
            $officeId = (int) $assignment['department_id'];
            $type = $assignment['assignment_type'];
            $reviewOfficeId = 0;
            if ($type === 'staff' && in_array($offices[$officeId]['office_level'] ?? '', ['division', 'unit'], true)) {
                $reviewOfficeId = $officeId;
            } elseif ($type === 'unit') {
                $reviewOfficeId = (int) ($offices[$officeId]['parent_id'] ?? 0);
            }
            if ($reviewOfficeId > 0) {
                $reviewAssignments = array_filter($assignments, static function ($candidate) use ($reviewOfficeId) {
                    return $candidate['assignment_type'] !== 'staff'
                        && (int) $candidate['department_id'] === $reviewOfficeId;
                });
                if ($reviewAssignments) {
                    foreach ($reviewAssignments as $reviewAssignment) {
                        $addAssignmentManager($reviewAssignment);
                    }
                } else {
                    $addManagers($reviewOfficeId);
                }
            }
        }

        foreach ($assignments as $assignment) {
            if (in_array($assignment['status'], ['Completed', 'Confirmed'], true)) {
                continue;
            }
            if ($assignment['assignment_type'] === 'staff') {
                $addPerson($assignment['user_id'] ?? 0);
                continue;
            }
            $officeId = (int) $assignment['department_id'];
            $type = $assignment['assignment_type'];
            $waiting = false;
            foreach ($assignments as $child) {
                if (in_array($child['status'], ['Confirmed', 'Cancelled'], true)) {
                    continue;
                }
                $childOfficeId = (int) $child['department_id'];
                $isDirectStaff = $child['assignment_type'] === 'staff' && $childOfficeId === $officeId;
                $isUnit = $type === 'division' && $child['assignment_type'] === 'unit'
                    && (int) ($offices[$childOfficeId]['parent_id'] ?? 0) === $officeId;
                $isDivision = $type === 'department' && $child['assignment_type'] === 'division'
                    && (int) ($offices[$childOfficeId]['parent_id'] ?? 0) === $officeId;
                if (($isDirectStaff || $isUnit || $isDivision)
                    && !($type === 'department' && $child['status'] === 'Completed')) {
                    $waiting = true;
                    break;
                }
            }
            // Receipt and draft finalization are immediate actions; receipt keeps the name visible.
            if (!$waiting || in_array($assignment['status'], ['Draft', 'Released', 'Delegated'], true)) {
                $addAssignmentManager($assignment);
            }
        }

        return array_values($names);
    }

    public static function label(array $names): string
    {
        return $names ? implode(', ', $names) : 'No pending action';
    }
}
