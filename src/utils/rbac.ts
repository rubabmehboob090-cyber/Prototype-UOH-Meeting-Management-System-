import { User, UserRole, Meeting } from '../types/index.ts';

export interface RolePermissions {
  canCreateMeeting: boolean;
  canApproveMeetings: boolean;
  canManageRooms: boolean;
  canViewAuditLogs: boolean;
  canViewReports: boolean;
  canManageMoM: boolean;
  canAssignActionItems: boolean;
  allowedViews: string[];
  roleDisplayName: string;
  portalTitle: string;
  portalDescription: string;
}

export function getRolePermissions(role: UserRole): RolePermissions {
  switch (role) {
    case 'Faculty/Staff':
      return {
        canCreateMeeting: true,
        canApproveMeetings: false,
        canManageRooms: false,
        canViewAuditLogs: false,
        canViewReports: false,
        canManageMoM: false,
        canAssignActionItems: false,
        allowedViews: ['dashboard', 'schedule', 'calendar', 'action-items', 'minutes'],
        roleDisplayName: 'Faculty Member',
        portalTitle: 'Faculty Academic Portal',
        portalDescription: 'Submit meeting requests, view assigned schedule, respond to RSVPs, track tasks, and review published minutes.',
      };

    case 'HOD':
    case 'Dean':
    case 'Director':
    case 'Committee Chair':
      return {
        canCreateMeeting: true,
        canApproveMeetings: true,
        canManageRooms: false,
        canViewAuditLogs: false,
        canViewReports: true,
        canManageMoM: true,
        canAssignActionItems: true,
        allowedViews: ['dashboard', 'schedule', 'approvals', 'calendar', 'action-items', 'minutes', 'reports'],
        roleDisplayName: `${role} (Academic Authority)`,
        portalTitle: 'Departmental & Leadership Portal',
        portalDescription: 'Schedule departmental meetings, authorize faculty requests, track action items, and publish statutory minutes.',
      };

    case 'Room Manager':
    case 'Office Admin':
      return {
        canCreateMeeting: true,
        canApproveMeetings: true,
        canManageRooms: true,
        canViewAuditLogs: false,
        canViewReports: true,
        canManageMoM: false,
        canAssignActionItems: true,
        allowedViews: ['dashboard', 'rooms', 'approvals', 'calendar', 'reports'],
        roleDisplayName: 'Estate & Room Manager',
        portalTitle: 'Campus Venue Operations Center',
        portalDescription: 'Manage room availability, review hall reservations, resolve venue double-booking conflicts, and monitor room utilization.',
      };

    case 'Registrar':
    case 'Super Admin':
    default:
      return {
        canCreateMeeting: true,
        canApproveMeetings: true,
        canManageRooms: true,
        canViewAuditLogs: true,
        canViewReports: true,
        canManageMoM: true,
        canAssignActionItems: true,
        allowedViews: [
          'dashboard', 'schedule', 'approvals', 'calendar', 
          'rooms', 'action-items', 'minutes', 'reports', 'audit-logs'
        ],
        roleDisplayName: 'Statutory Administration',
        portalTitle: 'University Executive Management System',
        portalDescription: 'Centralized governance of statutory council meetings (Syndicate, Senate), room conflict engine, approval workflows, and audit logs.',
      };
  }
}

/**
 * Filter meetings relevant for the logged-in user according to their role.
 * - Faculty: Only meetings where they are a participant OR university-wide public events.
 * - HOD/Dean/Director: Meetings in their department OR where they are a participant/requester OR university-wide.
 * - Room Manager: All room-bound meetings.
 * - Admin/Registrar: All meetings.
 */
export function filterMeetingsForRole(meetings: Meeting[], user: User): Meeting[] {
  if (!user) return meetings;

  if (user.role === 'Faculty/Staff') {
    return meetings.filter((m) => {
      const isParticipant = m.participants.some((p) => p.userId === user.id);
      const isRequester = m.requesterId === user.id;
      const isPublic = m.isUniversityWideEvent;
      return isParticipant || isRequester || isPublic;
    });
  }

  if (['HOD', 'Dean', 'Director', 'Committee Chair'].includes(user.role)) {
    return meetings.filter((m) => {
      const isDept = m.departmentId === user.departmentId;
      const isParticipant = m.participants.some((p) => p.userId === user.id);
      const isRequester = m.requesterId === user.id;
      const isPublic = m.isUniversityWideEvent;
      return isDept || isParticipant || isRequester || isPublic;
    });
  }

  return meetings;
}
