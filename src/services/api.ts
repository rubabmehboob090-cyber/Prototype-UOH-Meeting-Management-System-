import { 
  User, Department, Room, Meeting, Approval, Notification, 
  MinutesOfMeeting, ActionItem, AuditLog, AttendanceRecord, 
  ConflictCheckResult, SystemStats 
} from '../types/index';
import { 
  seedDepartments, seedUsers, seedRooms, seedMeetings, seedStats 
} from './mockSeedData';

const API_BASE = '/api';

export async function fetchStats(): Promise<SystemStats> {
  try {
    const res = await fetch(`${API_BASE}/stats`);
    if (!res.ok) throw new Error('Failed to fetch stats');
    return await res.json();
  } catch (err) {
    console.warn('API /stats fetch failed, using fallback seed data:', err);
    return seedStats;
  }
}

export async function fetchUsers(): Promise<User[]> {
  try {
    const res = await fetch(`${API_BASE}/users`);
    if (!res.ok) throw new Error('Failed to fetch users');
    return await res.json();
  } catch (err) {
    console.warn('API /users fetch failed, using fallback seed data:', err);
    return seedUsers;
  }
}

export async function fetchDepartments(): Promise<Department[]> {
  try {
    const res = await fetch(`${API_BASE}/departments`);
    if (!res.ok) throw new Error('Failed to fetch departments');
    return await res.json();
  } catch (err) {
    console.warn('API /departments fetch failed, using fallback seed data:', err);
    return seedDepartments;
  }
}

export async function fetchRooms(): Promise<Room[]> {
  try {
    const res = await fetch(`${API_BASE}/rooms`);
    if (!res.ok) throw new Error('Failed to fetch rooms');
    return await res.json();
  } catch (err) {
    console.warn('API /rooms fetch failed, using fallback seed data:', err);
    return seedRooms;
  }
}

export async function createRoom(roomData: Partial<Room>): Promise<Room> {
  try {
    const res = await fetch(`${API_BASE}/rooms`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(roomData)
    });
    if (!res.ok) throw new Error('Failed to create room');
    return await res.json();
  } catch (err) {
    const newRoom: Room = {
      id: `room-${Date.now()}`,
      name: roomData.name || 'New Room',
      building: roomData.building || 'Main Block',
      capacity: roomData.capacity || 20,
      facilities: roomData.facilities || [],
      requiresApproval: !!roomData.requiresApproval,
      isActive: true
    };
    seedRooms.push(newRoom);
    return newRoom;
  }
}

export async function checkConflict(params: {
  roomId?: string;
  date: string;
  startTime: string;
  endTime: string;
  participantUserIds?: string[];
  ignoreMeetingId?: string;
}): Promise<ConflictCheckResult> {
  try {
    const res = await fetch(`${API_BASE}/meetings/check-conflict`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(params)
    });
    if (!res.ok) throw new Error('Failed to check conflict');
    return await res.json();
  } catch (err) {
    return { hasConflict: false, conflicts: [], suggestions: [] };
  }
}

export async function fetchMeetings(params?: {
  status?: string;
  departmentId?: string;
  roomId?: string;
  date?: string;
}): Promise<Meeting[]> {
  try {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.departmentId) query.append('departmentId', params.departmentId);
    if (params?.roomId) query.append('roomId', params.roomId);
    if (params?.date) query.append('date', params.date);

    const res = await fetch(`${API_BASE}/meetings?${query.toString()}`);
    if (!res.ok) throw new Error('Failed to fetch meetings');
    return await res.json();
  } catch (err) {
    console.warn('API /meetings fetch failed, using fallback seed data:', err);
    let result = [...seedMeetings];
    if (params?.status) result = result.filter((m) => m.status === params.status);
    if (params?.departmentId) result = result.filter((m) => m.departmentId === params.departmentId);
    if (params?.roomId) result = result.filter((m) => m.roomId === params.roomId);
    if (params?.date) result = result.filter((m) => m.date === params.date);
    return result;
  }
}

export async function fetchMeetingById(id: string): Promise<Meeting> {
  try {
    const res = await fetch(`${API_BASE}/meetings/${id}`);
    if (!res.ok) throw new Error('Failed to fetch meeting details');
    return await res.json();
  } catch (err) {
    const found = seedMeetings.find((m) => m.id === id);
    if (found) return found;
    throw new Error('Meeting not found');
  }
}

export async function createMeeting(meetingData: any): Promise<{ meeting: Meeting; conflictResult: ConflictCheckResult }> {
  try {
    const res = await fetch(`${API_BASE}/meetings`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(meetingData)
    });
    if (!res.ok) throw new Error('Failed to create meeting request');
    return await res.json();
  } catch (err) {
    const newM: Meeting = {
      id: `mtg-${Date.now()}`,
      title: meetingData.title || 'Untitled Meeting',
      description: meetingData.description || '',
      meetingType: meetingData.meetingType || 'Departmental',
      departmentId: meetingData.departmentId || 'dept-1',
      requesterId: meetingData.requesterId || 'user-1',
      chairId: meetingData.chairId || 'user-1',
      roomId: meetingData.roomId,
      date: meetingData.date || new Date().toISOString().split('T')[0],
      startTime: meetingData.startTime || '10:00',
      endTime: meetingData.endTime || '11:00',
      mode: meetingData.mode || 'In-Person',
      status: 'Pending Approval',
      participants: (meetingData.participantUserIds || []).map((uId: string) => ({
        userId: uId,
        roleInMeeting: 'Member',
        status: 'Invited'
      })),
      agendaItems: meetingData.agendaItems || [],
      createdAt: new Date().toISOString().split('T')[0],
      updatedAt: new Date().toISOString().split('T')[0]
    };
    seedMeetings.push(newM);
    return {
      meeting: newM,
      conflictResult: { hasConflict: false, conflicts: [], suggestions: [] }
    };
  }
}

export async function updateMeetingStatus(
  id: string, 
  status: string, 
  comments?: string, 
  approverId?: string
): Promise<Meeting> {
  try {
    const res = await fetch(`${API_BASE}/meetings/${id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status, comments, approverId })
    });
    if (!res.ok) throw new Error('Failed to update meeting status');
    return await res.json();
  } catch (err) {
    const m = seedMeetings.find((mt) => mt.id === id);
    if (m) {
      m.status = status as any;
      return m;
    }
    throw new Error('Meeting not found');
  }
}

export async function fetchApprovals(params?: { userId?: string; role?: string; departmentId?: string }): Promise<any[]> {
  try {
    const query = new URLSearchParams();
    if (params?.userId) query.append('userId', params.userId);
    if (params?.role) query.append('role', params.role);
    if (params?.departmentId) query.append('departmentId', params.departmentId);

    const res = await fetch(`${API_BASE}/approvals?${query.toString()}`);
    if (!res.ok) throw new Error('Failed to fetch approvals queue');
    return await res.json();
  } catch (err) {
    return seedMeetings
      .filter((m) => m.status === 'Pending Approval')
      .map((m) => ({
        meeting: m,
        requester: seedUsers.find((u) => u.id === m.requesterId),
        room: seedRooms.find((r) => r.id === m.roomId),
        conflictAnalysis: { hasConflict: false, conflicts: [], suggestions: [] }
      }));
  }
}

export async function fetchMinutes(meetingId: string): Promise<MinutesOfMeeting | null> {
  try {
    const res = await fetch(`${API_BASE}/minutes/${meetingId}`);
    if (!res.ok) throw new Error('Failed to fetch minutes');
    return await res.json();
  } catch (err) {
    return null;
  }
}

export async function saveMinutes(data: Partial<MinutesOfMeeting>): Promise<MinutesOfMeeting> {
  try {
    const res = await fetch(`${API_BASE}/minutes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    if (!res.ok) throw new Error('Failed to save minutes');
    return await res.json();
  } catch (err) {
    return {
      id: `mom-${Date.now()}`,
      meetingId: data.meetingId || 'mtg-101',
      authorId: data.authorId || 'user-1',
      summary: data.summary || '',
      keyDecisions: data.keyDecisions || [],
      status: 'Published',
      createdAt: new Date().toISOString().split('T')[0]
    };
  }
}

export async function fetchAttendance(meetingId: string): Promise<AttendanceRecord[]> {
  try {
    const res = await fetch(`${API_BASE}/attendance/${meetingId}`);
    if (!res.ok) throw new Error('Failed to fetch attendance');
    return await res.json();
  } catch (err) {
    return [];
  }
}

export async function saveAttendance(meetingId: string, records: AttendanceRecord[]): Promise<void> {
  try {
    await fetch(`${API_BASE}/attendance/${meetingId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ records })
    });
  } catch (err) {
    console.warn('saveAttendance offline fallback');
  }
}

export async function fetchActionItems(params?: { meetingId?: string; assigneeId?: string }): Promise<ActionItem[]> {
  try {
    const query = new URLSearchParams();
    if (params?.meetingId) query.append('meetingId', params.meetingId);
    if (params?.assigneeId) query.append('assigneeId', params.assigneeId);

    const res = await fetch(`${API_BASE}/action-items?${query.toString()}`);
    if (!res.ok) throw new Error('Failed to fetch action items');
    return await res.json();
  } catch (err) {
    return [];
  }
}

export async function createActionItem(data: Partial<ActionItem>): Promise<ActionItem> {
  try {
    const res = await fetch(`${API_BASE}/action-items`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    if (!res.ok) throw new Error('Failed to create action item');
    return await res.json();
  } catch (err) {
    return {
      id: `act-${Date.now()}`,
      meetingId: data.meetingId || '',
      title: data.title || '',
      assigneeId: data.assigneeId || '',
      deadline: data.deadline || '',
      priority: data.priority || 'Medium',
      status: 'Pending',
      createdAt: new Date().toISOString().split('T')[0]
    };
  }
}

export async function updateActionItemStatus(id: string, status: string, notes?: string): Promise<ActionItem> {
  try {
    const res = await fetch(`${API_BASE}/action-items/${id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status, notes })
    });
    if (!res.ok) throw new Error('Failed to update action item');
    return await res.json();
  } catch (err) {
    return {
      id,
      meetingId: '',
      title: '',
      assigneeId: '',
      deadline: '',
      priority: 'Medium',
      status: status as any,
      notes,
      createdAt: ''
    };
  }
}

export async function fetchNotifications(userId: string): Promise<Notification[]> {
  try {
    const res = await fetch(`${API_BASE}/notifications/${userId}`);
    if (!res.ok) throw new Error('Failed to fetch notifications');
    return await res.json();
  } catch (err) {
    return [];
  }
}

export async function markNotificationRead(id: string): Promise<void> {
  try {
    await fetch(`${API_BASE}/notifications/${id}/read`, { method: 'PATCH' });
  } catch (err) {
    // silent
  }
}

export async function fetchAuditLogs(): Promise<AuditLog[]> {
  try {
    const res = await fetch(`${API_BASE}/audit-logs`);
    if (!res.ok) throw new Error('Failed to fetch audit logs');
    return await res.json();
  } catch (err) {
    return [];
  }
}
