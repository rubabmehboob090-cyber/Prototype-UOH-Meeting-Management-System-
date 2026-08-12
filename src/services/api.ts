import { 
  User, Department, Room, Meeting, Approval, Notification, 
  MinutesOfMeeting, ActionItem, AuditLog, AttendanceRecord, 
  ConflictCheckResult, SystemStats 
} from '../types/index.ts';

const API_BASE = '/api';

export async function fetchStats(): Promise<SystemStats> {
  const res = await fetch(`${API_BASE}/stats`);
  if (!res.ok) throw new Error('Failed to fetch stats');
  return res.json();
}

export async function fetchUsers(): Promise<User[]> {
  const res = await fetch(`${API_BASE}/users`);
  if (!res.ok) throw new Error('Failed to fetch users');
  return res.json();
}

export async function fetchDepartments(): Promise<Department[]> {
  const res = await fetch(`${API_BASE}/departments`);
  if (!res.ok) throw new Error('Failed to fetch departments');
  return res.json();
}

export async function fetchRooms(): Promise<Room[]> {
  const res = await fetch(`${API_BASE}/rooms`);
  if (!res.ok) throw new Error('Failed to fetch rooms');
  return res.json();
}

export async function createRoom(roomData: Partial<Room>): Promise<Room> {
  const res = await fetch(`${API_BASE}/rooms`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(roomData)
  });
  if (!res.ok) throw new Error('Failed to create room');
  return res.json();
}

export async function checkConflict(params: {
  roomId?: string;
  date: string;
  startTime: string;
  endTime: string;
  participantUserIds?: string[];
  ignoreMeetingId?: string;
}): Promise<ConflictCheckResult> {
  const res = await fetch(`${API_BASE}/meetings/check-conflict`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(params)
  });
  if (!res.ok) throw new Error('Failed to check conflict');
  return res.json();
}

export async function fetchMeetings(params?: {
  status?: string;
  departmentId?: string;
  roomId?: string;
  date?: string;
}): Promise<Meeting[]> {
  const query = new URLSearchParams();
  if (params?.status) query.append('status', params.status);
  if (params?.departmentId) query.append('departmentId', params.departmentId);
  if (params?.roomId) query.append('roomId', params.roomId);
  if (params?.date) query.append('date', params.date);

  const res = await fetch(`${API_BASE}/meetings?${query.toString()}`);
  if (!res.ok) throw new Error('Failed to fetch meetings');
  return res.json();
}

export async function fetchMeetingById(id: string): Promise<Meeting> {
  const res = await fetch(`${API_BASE}/meetings/${id}`);
  if (!res.ok) throw new Error('Failed to fetch meeting details');
  return res.json();
}

export async function createMeeting(meetingData: any): Promise<{ meeting: Meeting; conflictResult: ConflictCheckResult }> {
  const res = await fetch(`${API_BASE}/meetings`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(meetingData)
  });
  if (!res.ok) throw new Error('Failed to create meeting request');
  return res.json();
}

export async function updateMeetingStatus(
  id: string, 
  status: string, 
  comments?: string, 
  approverId?: string
): Promise<Meeting> {
  const res = await fetch(`${API_BASE}/meetings/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status, comments, approverId })
  });
  if (!res.ok) throw new Error('Failed to update meeting status');
  return res.json();
}

export async function fetchApprovals(params?: { userId?: string; role?: string; departmentId?: string }): Promise<any[]> {
  const query = new URLSearchParams();
  if (params?.userId) query.append('userId', params.userId);
  if (params?.role) query.append('role', params.role);
  if (params?.departmentId) query.append('departmentId', params.departmentId);

  const res = await fetch(`${API_BASE}/approvals?${query.toString()}`);
  if (!res.ok) throw new Error('Failed to fetch approvals queue');
  return res.json();
}

export async function fetchMinutes(meetingId: string): Promise<MinutesOfMeeting | null> {
  const res = await fetch(`${API_BASE}/minutes/${meetingId}`);
  if (!res.ok) throw new Error('Failed to fetch minutes');
  return res.json();
}

export async function saveMinutes(data: Partial<MinutesOfMeeting>): Promise<MinutesOfMeeting> {
  const res = await fetch(`${API_BASE}/minutes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error('Failed to save minutes');
  return res.json();
}

export async function fetchAttendance(meetingId: string): Promise<AttendanceRecord[]> {
  const res = await fetch(`${API_BASE}/attendance/${meetingId}`);
  if (!res.ok) throw new Error('Failed to fetch attendance');
  return res.json();
}

export async function saveAttendance(meetingId: string, records: AttendanceRecord[]): Promise<void> {
  const res = await fetch(`${API_BASE}/attendance/${meetingId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ records })
  });
  if (!res.ok) throw new Error('Failed to save attendance');
}

export async function fetchActionItems(params?: { meetingId?: string; assigneeId?: string }): Promise<ActionItem[]> {
  const query = new URLSearchParams();
  if (params?.meetingId) query.append('meetingId', params.meetingId);
  if (params?.assigneeId) query.append('assigneeId', params.assigneeId);

  const res = await fetch(`${API_BASE}/action-items?${query.toString()}`);
  if (!res.ok) throw new Error('Failed to fetch action items');
  return res.json();
}

export async function createActionItem(data: Partial<ActionItem>): Promise<ActionItem> {
  const res = await fetch(`${API_BASE}/action-items`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error('Failed to create action item');
  return res.json();
}

export async function updateActionItemStatus(id: string, status: string, notes?: string): Promise<ActionItem> {
  const res = await fetch(`${API_BASE}/action-items/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status, notes })
  });
  if (!res.ok) throw new Error('Failed to update action item');
  return res.json();
}

export async function fetchNotifications(userId: string): Promise<Notification[]> {
  const res = await fetch(`${API_BASE}/notifications/${userId}`);
  if (!res.ok) throw new Error('Failed to fetch notifications');
  return res.json();
}

export async function markNotificationRead(id: string): Promise<void> {
  await fetch(`${API_BASE}/notifications/${id}/read`, { method: 'PATCH' });
}

export async function fetchAuditLogs(): Promise<AuditLog[]> {
  const res = await fetch(`${API_BASE}/audit-logs`);
  if (!res.ok) throw new Error('Failed to fetch audit logs');
  return res.json();
}
