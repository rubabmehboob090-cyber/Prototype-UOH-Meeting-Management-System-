export type UserRole = 
  | 'Super Admin'
  | 'Registrar'
  | 'Dean'
  | 'Director'
  | 'HOD'
  | 'Faculty/Staff'
  | 'Office Admin'
  | 'Room Manager'
  | 'Committee Chair'
  | 'External Guest';

export type DepartmentCategory = 'Academic' | 'Administrative' | 'Directorate' | 'Executive';

export interface Department {
  id: string;
  name: string;
  code: string;
  category: DepartmentCategory;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  departmentId: string;
  designation: string;
  phone?: string;
  avatar?: string;
}

export interface Room {
  id: string;
  name: string;
  building: string;
  capacity: number;
  facilities: string[];
  requiresApproval: boolean;
  managerId?: string;
  isActive: boolean;
}

export type MeetingType = 
  | 'Departmental'
  | 'Academic Council'
  | 'Syndicate'
  | 'Senate'
  | 'ORIC / Research'
  | 'Committee'
  | 'Routine Admin'
  | 'Emergency';

export type MeetingStatus = 
  | 'Pending Approval'
  | 'Approved'
  | 'Rejected'
  | 'Rescheduled'
  | 'Cancelled'
  | 'Completed';

export interface Participant {
  userId: string;
  roleInMeeting: 'Chair' | 'Secretary' | 'Member' | 'Guest';
  status: 'Invited' | 'Accepted' | 'Declined' | 'Tentative';
  attended?: boolean;
}

export interface Meeting {
  id: string;
  title: string;
  description: string;
  meetingType: MeetingType;
  departmentId: string;
  requesterId: string;
  chairId: string;
  roomId?: string;
  locationDetails?: string; // For online or custom location
  date: string; // YYYY-MM-DD
  startTime: string; // HH:mm
  endTime: string; // HH:mm
  mode: 'In-Person' | 'Online' | 'Hybrid';
  onlineLink?: string;
  status: MeetingStatus;
  participants: Participant[];
  agendaItems: string[];
  agendaFiles?: { name: string; url: string }[];
  isUniversityWideEvent?: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface Approval {
  id: string;
  meetingId: string;
  approverId: string;
  approverRole: string;
  status: 'Pending' | 'Approved' | 'Rejected';
  comments?: string;
  createdAt: string;
}

export interface Notification {
  id: string;
  userId: string;
  title: string;
  message: string;
  type: 'approval_required' | 'meeting_approved' | 'meeting_rejected' | 'conflict_alert' | 'reminder' | 'action_assigned';
  isRead: boolean;
  meetingId?: string;
  createdAt: string;
}

export interface AttendanceRecord {
  userId: string;
  status: 'Present' | 'Absent' | 'Excused';
  remarks?: string;
}

export interface MinutesOfMeeting {
  id: string;
  meetingId: string;
  authorId: string;
  summary: string;
  keyDecisions: string[];
  attachments?: { name: string; url: string }[];
  status: 'Draft' | 'Published';
  createdAt: string;
  publishedAt?: string;
}

export interface ActionItem {
  id: string;
  meetingId: string;
  title: string;
  assigneeId: string;
  deadline: string; // YYYY-MM-DD
  priority: 'High' | 'Medium' | 'Low';
  status: 'Pending' | 'In Progress' | 'Completed';
  notes?: string;
  createdAt: string;
}

export interface AuditLog {
  id: string;
  timestamp: string;
  userId: string;
  userName: string;
  action: string;
  details: string;
  ipAddress: string;
}

export interface ConflictDetail {
  type: 'room' | 'participant' | 'university_event';
  title: string;
  description: string;
  conflictingEntityName: string;
  existingMeetingTitle?: string;
  existingTime?: string;
}

export interface SmartSuggestion {
  roomId: string;
  roomName: string;
  capacity: number;
  date: string;
  startTime: string;
  endTime: string;
  reason: string;
}

export interface ConflictCheckResult {
  hasConflict: boolean;
  conflicts: ConflictDetail[];
  suggestions: SmartSuggestion[];
}

export interface SystemStats {
  totalMeetings: number;
  pendingApprovals: number;
  approvedMeetings: number;
  completedMeetings: number;
  totalRooms: number;
  roomUtilizationRate: number; // percentage
  activeActionItems: number;
}
