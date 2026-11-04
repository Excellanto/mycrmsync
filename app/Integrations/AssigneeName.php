<?php

namespace App\Integrations;

use Illuminate\Validation\ValidationException;

/**
 * Assignee display-name filter shared by MyCrmSync, Zoho, and GoHighLevel contact lists.
 * Matching is the full name with letter case ignored.
 */
final class AssigneeName
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function from(array $params): string
    {
        $primary = trim((string) ($params['assigned_to_name'] ?? ''));
        $alias = trim((string) ($params['assignedToName'] ?? ''));

        if ($primary !== '' && $alias !== '' && self::fold($primary) !== self::fold($alias)) {
            throw ValidationException::withMessages([
                'assigned_to_name' => ['assigned_to_name and assignedToName must be the same name.'],
            ]);
        }

        return $primary !== '' ? $primary : $alias;
    }

    /**
     * @param  list<array{id?: string, name?: string}>  $users
     * @return list<string>
     */
    public static function matchingIds(array $users, string $name): array
    {
        $needle = self::fold($name);

        if ($needle === '') {
            return [];
        }

        $ids = [];

        foreach ($users as $user) {
            $id = trim((string) ($user['id'] ?? ''));

            if ($id !== '' && self::fold((string) ($user['name'] ?? '')) === $needle) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $ownerIds
     */
    public static function zohoOwnerCriteria(array $ownerIds, string $term = ''): string
    {
        $parts = [];

        foreach ($ownerIds as $ownerId) {
            $ownerId = trim($ownerId);

            if ($ownerId !== '') {
                $parts[] = '(Owner:equals:'.self::escapeZoho($ownerId).')';
            }
        }

        if ($parts === []) {
            return '';
        }

        $owner = count($parts) === 1 ? $parts[0] : '('.implode('or', $parts).')';
        $term = trim($term);

        if ($term === '') {
            return $owner;
        }

        $escaped = self::escapeZoho($term);
        $text = '((Full_Name:starts_with:'.$escaped.')or(First_Name:starts_with:'.$escaped.')or(Last_Name:starts_with:'.$escaped.')or(Email:starts_with:'.$escaped.')or(Phone:starts_with:'.$escaped.'))';

        return '('.$owner.'and'.$text.')';
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, mixed>
     */
    public static function goHighLevelAssigneeFilter(array $userIds): array
    {
        $filters = [];

        foreach ($userIds as $userId) {
            $userId = trim($userId);

            if ($userId === '') {
                continue;
            }

            $filters[] = [
                'field' => 'assignedTo',
                'operator' => 'eq',
                'value' => $userId,
            ];
        }

        if (count($filters) === 1) {
            return $filters[0];
        }

        return [
            'group' => 'OR',
            'filters' => $filters,
        ];
    }

    private static function fold(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private static function escapeZoho(string $value): string
    {
        return str_replace(['\\', '(', ')', ','], ['\\\\', '\\(', '\\)', '\\,'], $value);
    }
}
