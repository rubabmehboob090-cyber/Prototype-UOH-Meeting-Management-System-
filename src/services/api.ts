import { 
  User, Department, Room, Meeting, Approval, Notification, 
  MinutesOfMeeting, ActionItem, AuditLog, AttendanceRecord, 
  ConflictCheckResult, ConflictDetail, SmartSuggestion, SystemStats 
} from '../types/index';
import { 
  seedDepartments, seedUsers, seedRooms, seedMeetings, seedStats 
} from './mockSeedData';

const API_BASE = '/api';

// Local storage keys
const STORAGE_KEYS = {
  MEETINGS: 'uoh_mms_meetings',
  ROOMS: 'uoh_mms_rooms',
  USERS: 'uoh_mms_users',
  DEPARTMENTS: 'uoh_mms_departments',
  ACTION_ITEMS: 'uoh_mms_action_items',
  MINUTES: 'uoh_mms_minutes',
  ATTENDANCE: 'uoh_mms_attendance',
  NOTIFICATIONS: 'uoh_mms_notifications',
  AUDIT_LOGS: 'uoh_mms_audit_logs',
};

// Safe LocalStorage helpers
function getStoredItem<T>(key: string, defaultVal: T): T {
  try {
    if (typeof window === 'undefined' || !window.localStorage) return defaultVal;
    const item = window.localStorage.getItem(key);
    if (!item) return defaultVal;
    return JSON.parse(item) as T;
  } catch {
    return defaultVal;
  }
}

function setStoredItem<T>(key: string, value: T): void {
  try {
    if (typeof window !== 'undefined' && window.localStorage) {
      window.localStorage.setItem(key, JSON.stringify(value));
    }
  } catch {
    // Ignore storage errors (quota exceeded / private browsing)
  }
}

// Initial Local Storage bootstrap
function initializeLocalStorage() {
  if (typeof window === 'undefined' || !window.localStorage) return;
  if (!window.localStorage.getItem(STORAGE_KEYS.MEETINGS)) {
    setStoredItem(STORAGE_KEYS.MEETINGS, seedMeetings);
  }
  if (!window.localStorage.getItem(STORAGE_KEYS.ROOMS)) {
    setStoredItem(STORAGE_KEYS.ROOMS, seedRooms);
  }
  if (!window.localStorage.getItem(STORAGE_KEYS.USERS)) {
    setStoredItem(STORAGE_KEYS.USERS, seedUsers);
  }
  if (!window.localStorage.getItem(STORAGE_KEYS.DEPARTMENTS)) {
    setStoredItem(STORAGE_KEYS.DEPARTMENTS, seedDepartments);
  }
}

// Run init
initializeLocalStorage();

/**
 * Safely fetches JSON from the API backend.
 * Returns null if network fails, status is not OK, or response is HTML (<!doctype html>)
 */
async function safeApiFetch<T>(url: string, options?: RequestInit): Promise<T | null> {
  try {
    const res = await fetch(url, options);
    if (!res.ok) return null;

    const contentType = res.headers.get('content-type');
    if (!contentType || !contentType.toLowerCase().includes('application/json')) {
      // Returned HTML (e.g. <!doctype html> SPA fallback) or non-JSON
      return null;
    }

    const data = await res.json();
    return data as T;
  } catch {
    return null;
  }
}

// Helper time calculation
function parseTimeToMinutes(timeStr: string): number {
  if (!timeStr) return 0;
  const [h, m] = timeStr.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
}

// Client-side conflict detection engine
function runLocalConflictCheck(params: {
  roomId?: string;
  date: string;
  startTime: string;
  endTime: string;
  participantUserIds?: string[];
  ignoreMeetingId?: string;
}): ConflictCheckResult {
  const { roomId, date, startTime, endTime, participantUserIds = [], ignoreMeetingId } = params;
  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  const rooms = getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);
  const users = getStoredItem<User[]>(STORAGE_KEYS.USERS, seedUsers);

  const reqStart = parseTimeToMinutes(startTime);
  const reqEnd = parseTimeToMinutes(endTime);

  const conflicts: ConflictDetail[] = [];

  const activeMeetings = meetings.filter(
    (m) =>
      m.date === date &&
      m.status !== 'Rejected' &&
      m.status !== 'Cancelled' &&
      (!ignoreMeetingId || m.id !== ignoreMeetingId)
  );

  for (const m of activeMeetings) {
    const mStart = parseTimeToMinutes(m.startTime);
    const mEnd = parseTimeToMinutes(m.endTime);
    const isOverlapping = reqStart < mEnd && reqEnd > mStart;

    if (isOverlapping) {
      if (roomId && m.roomId === roomId) {
        const roomObj = rooms.find((r) => r.id === roomId);
        conflicts.push({
          type: 'room',
          title: 'Room Double-Booking Conflict',
          description: `Room "${roomObj?.name || 'Selected Room'}" is already reserved for "${m.title}" during ${m.startTime} - ${m.endTime}.`,
          conflictingEntityName: roomObj?.name || 'Room',
          existingMeetingTitle: m.title,
          existingTime: `${m.startTime} - ${m.endTime}`
        });
      }

      for (const pId of participantUserIds) {
        if (m.participants.some((p) => p.userId === pId)) {
          const userObj = users.find((u) => u.id === pId);
          conflicts.push({
            type: 'participant',
            title: 'Participant Schedule Conflict',
            description: `${userObj?.name || 'Participant'} is already scheduled in meeting "${m.title}" during ${m.startTime} - ${m.endTime}.`,
            conflictingEntityName: userObj?.name || 'Participant',
            existingMeetingTitle: m.title,
            existingTime: `${m.startTime} - ${m.endTime}`
          });
        }
      }

      if (m.isUniversityWideEvent) {
        conflicts.push({
          type: 'university_event',
          title: 'University-Wide Official Event Overlap',
          description: `This slot overlaps with statutory University-Wide event "${m.title}" (${m.startTime} - ${m.endTime}).`,
          conflictingEntityName: m.title,
          existingMeetingTitle: m.title,
          existingTime: `${m.startTime} - ${m.endTime}`
        });
      }
    }
  }

  const suggestions: SmartSuggestion[] = [];
  if (conflicts.length > 0) {
    const requestedRoom = rooms.find((r) => r.id === roomId);
    const targetCapacity = requestedRoom ? requestedRoom.capacity : 20;

    const availableRooms = rooms.filter((r) => {
      if (!r.isActive) return false;
      const hasRoomOverlap = activeMeetings.some((m) => {
        const mStart = parseTimeToMinutes(m.startTime);
        const mEnd = parseTimeToMinutes(m.endTime);
        return m.roomId === r.id && reqStart < mEnd && reqEnd > mStart;
      });
      return !hasRoomOverlap && r.capacity >= targetCapacity - 10;
    });

    for (const altRoom of availableRooms.slice(0, 3)) {
      suggestions.push({
        roomId: altRoom.id,
        roomName: altRoom.name,
        capacity: altRoom.capacity,
        date: date,
        startTime: startTime,
        endTime: endTime,
        reason: `Room "${altRoom.name}" (Capacity: ${altRoom.capacity}) is available at ${startTime} - ${endTime}`
      });
    }
  }

  return {
    hasConflict: conflicts.length > 0,
    conflicts,
    suggestions
  };
}

// ---------------- API FUNCTIONS ----------------

export async function fetchStats(): Promise<SystemStats> {
  const apiData = await safeApiFetch<SystemStats>(`${API_BASE}/stats`);
  if (apiData) return apiData;

  // Fallback to locally calculated stats
  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  const rooms = getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);
  const actionItems = getStoredItem<ActionItem[]>(STORAGE_KEYS.ACTION_ITEMS, []);

  const todayStr = new Date().toISOString().split('T')[0];
  const todayMeetingsCount = meetings.filter((m) => m.date === todayStr && m.status === 'Approved').length;
  const roomUtilizationRate = rooms.length > 0 ? Math.min(100, Math.round((todayMeetingsCount / (rooms.length * 2)) * 100)) : 35;

  return {
    totalMeetings: meetings.length,
    pendingApprovals: meetings.filter((m) => m.status === 'Pending Approval').length,
    approvedMeetings: meetings.filter((m) => m.status === 'Approved').length,
    completedMeetings: meetings.filter((m) => m.status === 'Completed').length,
    totalRooms: rooms.length,
    roomUtilizationRate: roomUtilizationRate || 25,
    activeActionItems: actionItems.filter((a) => a.status !== 'Completed').length
  };
}

export async function fetchUsers(): Promise<User[]> {
  const apiData = await safeApiFetch<User[]>(`${API_BASE}/users`);
  if (apiData && Array.isArray(apiData)) {
    setStoredItem(STORAGE_KEYS.USERS, apiData);
    return apiData;
  }
  return getStoredItem<User[]>(STORAGE_KEYS.USERS, seedUsers);
}

export async function fetchDepartments(): Promise<Department[]> {
  const apiData = await safeApiFetch<Department[]>(`${API_BASE}/departments`);
  if (apiData && Array.isArray(apiData)) {
    setStoredItem(STORAGE_KEYS.DEPARTMENTS, apiData);
    return apiData;
  }
  return getStoredItem<Department[]>(STORAGE_KEYS.DEPARTMENTS, seedDepartments);
}

export async function fetchRooms(): Promise<Room[]> {
  const apiData = await safeApiFetch<Room[]>(`${API_BASE}/rooms`);
  if (apiData && Array.isArray(apiData)) {
    setStoredItem(STORAGE_KEYS.ROOMS, apiData);
    return apiData;
  }
  return getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);
}

export async function createRoom(roomData: Partial<Room>): Promise<Room> {
  const apiData = await safeApiFetch<Room>(`${API_BASE}/rooms`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(roomData)
  });

  const rooms = getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);
  const newRoom: Room = apiData || {
    id: `room-${Date.now()}`,
    name: roomData.name || 'New Room',
    building: roomData.building || 'Main Block',
    capacity: roomData.capacity || 20,
    facilities: roomData.facilities || [],
    requiresApproval: !!roomData.requiresApproval,
    managerId: roomData.managerId,
    isActive: true
  };

  if (!apiData) {
    rooms.push(newRoom);
    setStoredItem(STORAGE_KEYS.ROOMS, rooms);
  }

  return newRoom;
}

export async function checkConflict(params: {
  roomId?: string;
  date: string;
  startTime: string;
  endTime: string;
  participantUserIds?: string[];
  ignoreMeetingId?: string;
}): Promise<ConflictCheckResult> {
  const apiData = await safeApiFetch<ConflictCheckResult>(`${API_BASE}/meetings/check-conflict`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(params)
  });

  if (apiData) return apiData;
  return runLocalConflictCheck(params);
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

  const apiData = await safeApiFetch<Meeting[]>(`${API_BASE}/meetings?${query.toString()}`);
  if (apiData && Array.isArray(apiData)) {
    setStoredItem(STORAGE_KEYS.MEETINGS, apiData);
    return apiData;
  }

  let result = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  if (params?.status) result = result.filter((m) => m.status === params.status);
  if (params?.departmentId) result = result.filter((m) => m.departmentId === params.departmentId);
  if (params?.roomId) result = result.filter((m) => m.roomId === params.roomId);
  if (params?.date) result = result.filter((m) => m.date === params.date);
  return result;
}

export async function fetchMeetingById(id: string): Promise<Meeting> {
  const apiData = await safeApiFetch<Meeting>(`${API_BASE}/meetings/${id}`);
  if (apiData) return apiData;

  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  const found = meetings.find((m) => m.id === id);
  if (found) return found;
  throw new Error('Meeting not found');
}

export async function createMeeting(meetingData: any): Promise<{ meeting: Meeting; conflictResult: ConflictCheckResult }> {
  const apiData = await safeApiFetch<{ meeting: Meeting; conflictResult: ConflictCheckResult }>(`${API_BASE}/meetings`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(meetingData)
  });

  if (apiData) {
    const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
    meetings.unshift(apiData.meeting);
    setStoredItem(STORAGE_KEYS.MEETINGS, meetings);
    return apiData;
  }

  // Local fallback creation
  const rooms = getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);
  const users = getStoredItem<User[]>(STORAGE_KEYS.USERS, seedUsers);
  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);

  const conflictResult = runLocalConflictCheck({
    roomId: meetingData.roomId,
    date: meetingData.date,
    startTime: meetingData.startTime,
    endTime: meetingData.endTime,
    participantUserIds: meetingData.participantUserIds
  });

  const selectedRoom = rooms.find((r) => r.id === meetingData.roomId);
  const requiresRoomApproval = !!selectedRoom?.requiresApproval;
  const requesterUser = users.find((u) => u.id === meetingData.requesterId);

  const isSuperAdminOrRegistrar = requesterUser?.role === 'Super Admin' || requesterUser?.role === 'Registrar';
  const isHODorDean = requesterUser?.role === 'HOD' || requesterUser?.role === 'Dean' || requesterUser?.role === 'Director';
  const isInternalType = ['Departmental', 'ORIC / Research', 'Committee / Office', 'Routine Admin'].includes(meetingData.meetingType);

  let initialStatus: Meeting['status'] = 'Approved';
  if (isSuperAdminOrRegistrar) {
    initialStatus = 'Approved';
  } else if (isHODorDean && isInternalType && !requiresRoomApproval) {
    initialStatus = 'Approved';
  } else {
    initialStatus = 'Pending Approval';
  }

  const newMeeting: Meeting = {
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
    onlineLink: meetingData.onlineLink,
    status: initialStatus,
    isUniversityWideEvent: !!meetingData.isUniversityWideEvent,
    participants: (meetingData.participantUserIds || []).map((uId: string) => ({
      userId: uId,
      roleInMeeting: uId === meetingData.chairId ? 'Chair' : 'Member',
      status: 'Invited'
    })),
    agendaItems: meetingData.agendaItems || [],
    createdAt: new Date().toISOString().split('T')[0],
    updatedAt: new Date().toISOString().split('T')[0]
  };

  meetings.unshift(newMeeting);
  setStoredItem(STORAGE_KEYS.MEETINGS, meetings);

  return {
    meeting: newMeeting,
    conflictResult
  };
}

export async function updateMeetingStatus(
  id: string, 
  status: string, 
  comments?: string, 
  approverId?: string
): Promise<Meeting> {
  const apiData = await safeApiFetch<Meeting>(`${API_BASE}/meetings/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status, comments, approverId })
  });

  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  const mIndex = meetings.findIndex((mt) => mt.id === id);

  if (apiData) {
    if (mIndex !== -1) {
      meetings[mIndex] = apiData;
      setStoredItem(STORAGE_KEYS.MEETINGS, meetings);
    }
    return apiData;
  }

  if (mIndex !== -1) {
    meetings[mIndex].status = status as any;
    meetings[mIndex].updatedAt = new Date().toISOString().split('T')[0];
    setStoredItem(STORAGE_KEYS.MEETINGS, meetings);
    return meetings[mIndex];
  }

  throw new Error('Meeting not found');
}

export async function fetchApprovals(params?: { userId?: string; role?: string; departmentId?: string }): Promise<any[]> {
  const query = new URLSearchParams();
  if (params?.userId) query.append('userId', params.userId);
  if (params?.role) query.append('role', params.role);
  if (params?.departmentId) query.append('departmentId', params.departmentId);

  const apiData = await safeApiFetch<any[]>(`${API_BASE}/approvals?${query.toString()}`);
  if (apiData && Array.isArray(apiData)) return apiData;

  const meetings = getStoredItem<Meeting[]>(STORAGE_KEYS.MEETINGS, seedMeetings);
  const users = getStoredItem<User[]>(STORAGE_KEYS.USERS, seedUsers);
  const rooms = getStoredItem<Room[]>(STORAGE_KEYS.ROOMS, seedRooms);

  return meetings
    .filter((m) => m.status === 'Pending Approval')
    .map((m) => ({
      meeting: m,
      requester: users.find((u) => u.id === m.requesterId),
      room: rooms.find((r) => r.id === m.roomId),
      conflictAnalysis: { hasConflict: false, conflicts: [], suggestions: [] }
    }));
}

export async function fetchMinutes(meetingId: string): Promise<MinutesOfMeeting | null> {
  const apiData = await safeApiFetch<MinutesOfMeeting>(`${API_BASE}/minutes/${meetingId}`);
  if (apiData) return apiData;

  const allMinutes = getStoredItem<Record<string, MinutesOfMeeting>>(STORAGE_KEYS.MINUTES, {});
  return allMinutes[meetingId] || null;
}

export async function saveMinutes(data: Partial<MinutesOfMeeting>): Promise<MinutesOfMeeting> {
  const apiData = await safeApiFetch<MinutesOfMeeting>(`${API_BASE}/minutes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  const allMinutes = getStoredItem<Record<string, MinutesOfMeeting>>(STORAGE_KEYS.MINUTES, {});
  const newMinutes: MinutesOfMeeting = apiData || {
    id: `mom-${Date.now()}`,
    meetingId: data.meetingId || 'mtg-101',
    authorId: data.authorId || 'user-1',
    summary: data.summary || '',
    keyDecisions: data.keyDecisions || [],
    status: 'Published',
    createdAt: new Date().toISOString().split('T')[0]
  };

  allMinutes[newMinutes.meetingId] = newMinutes;
  setStoredItem(STORAGE_KEYS.MINUTES, allMinutes);
  return newMinutes;
}

export async function fetchAttendance(meetingId: string): Promise<AttendanceRecord[]> {
  const apiData = await safeApiFetch<AttendanceRecord[]>(`${API_BASE}/attendance/${meetingId}`);
  if (apiData && Array.isArray(apiData)) return apiData;

  const allAttendance = getStoredItem<Record<string, AttendanceRecord[]>>(STORAGE_KEYS.ATTENDANCE, {});
  return allAttendance[meetingId] || [];
}

export async function saveAttendance(meetingId: string, records: AttendanceRecord[]): Promise<void> {
  await safeApiFetch(`${API_BASE}/attendance/${meetingId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ records })
  });

  const allAttendance = getStoredItem<Record<string, AttendanceRecord[]>>(STORAGE_KEYS.ATTENDANCE, {});
  allAttendance[meetingId] = records;
  setStoredItem(STORAGE_KEYS.ATTENDANCE, allAttendance);
}

export async function fetchActionItems(params?: { meetingId?: string; assigneeId?: string }): Promise<ActionItem[]> {
  const query = new URLSearchParams();
  if (params?.meetingId) query.append('meetingId', params.meetingId);
  if (params?.assigneeId) query.append('assigneeId', params.assigneeId);

  const apiData = await safeApiFetch<ActionItem[]>(`${API_BASE}/action-items?${query.toString()}`);
  if (apiData && Array.isArray(apiData)) return apiData;

  let items = getStoredItem<ActionItem[]>(STORAGE_KEYS.ACTION_ITEMS, [
    {
      id: 'act-1',
      meetingId: 'mtg-101',
      title: 'Submit finalized BS AI Scheme of Studies to QEC for NOC clearance',
      assigneeId: 'user-4',
      deadline: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
      priority: 'High',
      status: 'In Progress',
      notes: 'Curriculum structure reviewed by department board',
      createdAt: new Date().toISOString().split('T')[0]
    },
    {
      id: 'act-2',
      meetingId: 'mtg-102',
      title: 'Draft standard MoU agreement for industry internship linkages',
      assigneeId: 'user-5',
      deadline: new Date(Date.now() + 10 * 86400000).toISOString().split('T')[0],
      priority: 'Medium',
      status: 'Pending',
      notes: '',
      createdAt: new Date().toISOString().split('T')[0]
    }
  ]);

  if (params?.meetingId) items = items.filter((a) => a.meetingId === params.meetingId);
  if (params?.assigneeId) items = items.filter((a) => a.assigneeId === params.assigneeId);
  return items;
}

export async function createActionItem(data: Partial<ActionItem>): Promise<ActionItem> {
  const apiData = await safeApiFetch<ActionItem>(`${API_BASE}/action-items`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  const items = getStoredItem<ActionItem[]>(STORAGE_KEYS.ACTION_ITEMS, []);
  const newItem: ActionItem = apiData || {
    id: `act-${Date.now()}`,
    meetingId: data.meetingId || '',
    title: data.title || 'Action Item',
    assigneeId: data.assigneeId || 'user-1',
    deadline: data.deadline || new Date().toISOString().split('T')[0],
    priority: data.priority || 'Medium',
    status: 'Pending',
    notes: data.notes || '',
    createdAt: new Date().toISOString().split('T')[0]
  };

  if (!apiData) {
    items.push(newItem);
    setStoredItem(STORAGE_KEYS.ACTION_ITEMS, items);
  }

  return newItem;
}

export async function updateActionItemStatus(id: string, status: string, notes?: string): Promise<ActionItem> {
  const apiData = await safeApiFetch<ActionItem>(`${API_BASE}/action-items/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status, notes })
  });

  const items = getStoredItem<ActionItem[]>(STORAGE_KEYS.ACTION_ITEMS, []);
  const item = items.find((a) => a.id === id);

  if (apiData) {
    if (item) {
      item.status = apiData.status;
      if (notes !== undefined) item.notes = notes;
      setStoredItem(STORAGE_KEYS.ACTION_ITEMS, items);
    }
    return apiData;
  }

  if (item) {
    item.status = status as any;
    if (notes !== undefined) item.notes = notes;
    setStoredItem(STORAGE_KEYS.ACTION_ITEMS, items);
    return item;
  }

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

export async function fetchNotifications(userId: string): Promise<Notification[]> {
  const apiData = await safeApiFetch<Notification[]>(`${API_BASE}/notifications/${userId}`);
  if (apiData && Array.isArray(apiData)) return apiData;

  const notifs = getStoredItem<Notification[]>(STORAGE_KEYS.NOTIFICATIONS, [
    {
      id: 'notif-1',
      userId: 'user-1',
      title: 'New Meeting Request Received',
      message: 'CS & IT Department submitted "CS & IT Departmental Board of Studies Prep" for approval.',
      type: 'approval_required',
      isRead: false,
      meetingId: 'mtg-103',
      createdAt: new Date().toISOString()
    },
    {
      id: 'notif-2',
      userId: 'user-1',
      title: 'Meeting Approved',
      message: 'Your meeting "UoH 18th Academic Council Session" has been approved.',
      type: 'meeting_approved',
      isRead: true,
      meetingId: 'mtg-101',
      createdAt: new Date().toISOString()
    }
  ]);

  return notifs.filter((n) => !userId || n.userId === userId);
}

export async function markNotificationRead(id: string): Promise<void> {
  await safeApiFetch(`${API_BASE}/notifications/${id}/read`, { method: 'PATCH' });

  const notifs = getStoredItem<Notification[]>(STORAGE_KEYS.NOTIFICATIONS, []);
  const n = notifs.find((item) => item.id === id);
  if (n) {
    n.isRead = true;
    setStoredItem(STORAGE_KEYS.NOTIFICATIONS, notifs);
  }
}

export async function fetchAuditLogs(): Promise<AuditLog[]> {
  const apiData = await safeApiFetch<AuditLog[]>(`${API_BASE}/audit-logs`);
  if (apiData && Array.isArray(apiData)) return apiData;

  return getStoredItem<AuditLog[]>(STORAGE_KEYS.AUDIT_LOGS, [
    {
      id: 'audit-1',
      action: 'MEETING_APPROVED',
      userId: 'user-2',
      userName: 'Prof. Dr. Muhammad Khan',
      details: 'Meeting auto-approved for Senate Hall reservation (ID: mtg-101).',
      ipAddress: '10.0.1.45',
      timestamp: new Date().toISOString()
    },
    {
      id: 'audit-2',
      action: 'MEETING_REQUESTED',
      userId: 'user-4',
      userName: 'Dr. Tariq Mahmood',
      details: 'Submitted CS & IT Departmental Board of Studies Prep meeting request (ID: mtg-103).',
      ipAddress: '10.0.2.19',
      timestamp: new Date().toISOString()
    }
  ]);
}
