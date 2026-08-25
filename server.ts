import 'dotenv/config';
import express from 'express';
import path from 'path';
import { createServer as createViteServer } from 'vite';
import type { 
  Department, User, Room, Meeting, Approval, Notification, 
  MinutesOfMeeting, ActionItem, AuditLog, AttendanceRecord,
  ConflictCheckResult, ConflictDetail, SmartSuggestion, SystemStats,
  ParticipantFreeSlot, GlobalClashItem
} from './src/types/index';

const app = express();
const PORT = 3000;

app.use(express.json());

// Enable CORS for all routes
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
  if (req.method === 'OPTIONS') {
    return res.sendStatus(200);
  }
  next();
});

// In-Memory Database initialized with UoH Seed Data
const departments: Department[] = [
  { id: 'dept-1', name: 'Registrar Office', code: 'REG', category: 'Administrative' },
  { id: 'dept-2', name: 'Department of Computer Science & IT', code: 'CSIT', category: 'Academic' },
  { id: 'dept-3', name: 'Department of Management Sciences', code: 'MS', category: 'Academic' },
  { id: 'dept-4', name: 'Office of Research Innovation & Commercialization', code: 'ORIC', category: 'Directorate' },
  { id: 'dept-5', name: 'Business Incubation Center', code: 'BIC', category: 'Directorate' },
  { id: 'dept-6', name: 'Quality Enhancement Cell', code: 'QEC', category: 'Directorate' },
  { id: 'dept-7', name: 'Examination Office', code: 'EXAM', category: 'Administrative' },
  { id: 'dept-8', name: 'Treasurer Office', code: 'TR', category: 'Administrative' },
  { id: 'dept-9', name: 'Works & Services Office', code: 'WKS', category: 'Administrative' },
  { id: 'dept-10', name: 'Faculty of Sciences / Dean Office', code: 'DEAN-SCI', category: 'Executive' },
];

const users: User[] = [
  {
    id: 'user-1',
    name: 'Dr. Ahmed',
    email: 'ahmed@uoh.edu.pk',
    role: 'Super Admin',
    departmentId: 'dept-1',
    designation: 'Assistant Registrar (Meetings)',
    phone: '+92 995 920601',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-2',
    name: 'Prof. Dr. Muhammad Khan',
    email: 'registrar@uoh.edu.pk',
    role: 'Registrar',
    departmentId: 'dept-1',
    designation: 'Registrar UoH',
    phone: '+92 995 920600',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-3',
    name: 'Prof. Dr. Zahid Hussain',
    email: 'zahid.hussain@uoh.edu.pk',
    role: 'Dean',
    departmentId: 'dept-10',
    designation: 'Dean, Faculty of Sciences',
    phone: '+92 995 920605',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-4',
    name: 'Dr. Tariq Mahmood',
    email: 'hod.cs@uoh.edu.pk',
    role: 'HOD',
    departmentId: 'dept-2',
    designation: 'HOD Computer Science & IT',
    phone: '+92 995 920620',
    avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-5',
    name: 'Engr. Asim Rauf',
    email: 'director.oric@uoh.edu.pk',
    role: 'Director',
    departmentId: 'dept-4',
    designation: 'Director ORIC',
    phone: '+92 995 920630',
    avatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-6',
    name: 'Mr. Khalid Pervez',
    email: 'khalid.rooms@uoh.edu.pk',
    role: 'Room Manager',
    departmentId: 'dept-9',
    designation: 'Estate Officer & Room Manager',
    phone: '+92 995 920612',
    avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-7',
    name: 'Dr. Ayesha Siddiqui',
    email: 'ayesha.siddiqui@uoh.edu.pk',
    role: 'Faculty/Staff',
    departmentId: 'dept-2',
    designation: 'Associate Professor, CS & IT',
    phone: '+92 995 920622',
    avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80'
  },
  {
    id: 'user-8',
    name: 'Mr. Rizwan Malik',
    email: 'rizwan.admin@uoh.edu.pk',
    role: 'Office Admin',
    departmentId: 'dept-1',
    designation: 'Office Admin Officer',
    phone: '+92 995 920610',
    avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=150&auto=format&fit=crop&q=80'
  }
];

const rooms: Room[] = [
  {
    id: 'room-1',
    name: 'Senate Hall',
    building: 'Main Administration Block (3rd Floor)',
    capacity: 65,
    facilities: ['Video Conferencing System', 'Conference Audio Microphones', 'Air Conditioning', 'Multimedia HD Projector', 'Executive Catering Space'],
    requiresApproval: true,
    managerId: 'user-6',
    isActive: true
  },
  {
    id: 'room-2',
    name: 'Main Auditorium',
    building: 'Central Academic Block',
    capacity: 350,
    facilities: ['Stage Sound System', 'Dual Projection Screen', 'Air Conditioning', 'VIP Seating', 'Stage Lighting'],
    requiresApproval: true,
    managerId: 'user-6',
    isActive: true
  },
  {
    id: 'room-3',
    name: 'CS Seminar Hall',
    building: 'CS & IT Block (1st Floor)',
    capacity: 85,
    facilities: ['Interactive Smart Board', 'Air Conditioning', 'Multimedia Projector', 'Podium Mic'],
    requiresApproval: false,
    managerId: 'user-4',
    isActive: true
  },
  {
    id: 'room-4',
    name: 'Video Conference Room 102',
    building: 'IT & Digital Resource Center',
    capacity: 30,
    facilities: ['Fiber VC Terminal', 'Dual Display Screens', 'Air Conditioning', 'High Speed Wi-Fi'],
    requiresApproval: true,
    managerId: 'user-6',
    isActive: true
  },
  {
    id: 'room-5',
    name: 'Syndicate Conference Room',
    building: 'Registrar Block',
    capacity: 35,
    facilities: ['Executive Oval Conference Table', 'Delegates Gooseneck Mics', 'Air Conditioning', 'Smart TV'],
    requiresApproval: true,
    managerId: 'user-2',
    isActive: true
  },
  {
    id: 'room-6',
    name: 'Dean Meeting Room A',
    building: 'Sciences Block (Ground Floor)',
    capacity: 18,
    facilities: ['Whiteboard', 'LED Display Screen', 'Air Conditioning'],
    requiresApproval: false,
    managerId: 'user-3',
    isActive: true
  }
];

// Seed Today's date string YYYY-MM-DD
const today = new Date();
const todayStr = today.toISOString().split('T')[0];

// Helper to get relative date
const getRelativeDateStr = (days: number) => {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().split('T')[0];
};

const meetings: Meeting[] = [
  {
    id: 'mtg-101',
    title: 'UoH 18th Academic Council Session',
    description: 'Statutory meeting of Academic Council to approve new BS Artificial Intelligence curriculum and degree regulations.',
    meetingType: 'Academic Council',
    departmentId: 'dept-1',
    requesterId: 'user-2',
    chairId: 'user-2',
    roomId: 'room-1',
    date: todayStr,
    startTime: '10:00',
    endTime: '12:30',
    mode: 'In-Person',
    status: 'Approved',
    isUniversityWideEvent: true,
    participants: [
      { userId: 'user-2', roleInMeeting: 'Chair', status: 'Accepted', attended: true },
      { userId: 'user-3', roleInMeeting: 'Member', status: 'Accepted', attended: true },
      { userId: 'user-4', roleInMeeting: 'Member', status: 'Accepted', attended: true },
      { userId: 'user-5', roleInMeeting: 'Member', status: 'Accepted', attended: false },
      { userId: 'user-7', roleInMeeting: 'Secretary', status: 'Accepted', attended: true },
    ],
    agendaItems: [
      'Approval of minutes of the 17th Academic Council Meeting',
      'Review of BS Artificial Intelligence curriculum proposed by CSIT Department',
      'Approval of Quality Enhancement Cell (QEC) annual audit report',
      'Matters regarding semester terminal examinations date sheet'
    ],
    createdAt: getRelativeDateStr(-5),
    updatedAt: getRelativeDateStr(-1)
  },
  {
    id: 'mtg-102',
    title: 'ORIC Research Grant Allocation & Industry Linkages',
    description: 'Quarterly review of commercialization proposals and distribution of university internal research funding.',
    meetingType: 'ORIC / Research',
    departmentId: 'dept-4',
    requesterId: 'user-5',
    chairId: 'user-5',
    roomId: 'room-4',
    date: todayStr,
    startTime: '14:00',
    endTime: '15:30',
    mode: 'Hybrid',
    onlineLink: 'https://meet.uoh.edu.pk/oric-review-2026',
    status: 'Approved',
    isUniversityWideEvent: false,
    participants: [
      { userId: 'user-5', roleInMeeting: 'Chair', status: 'Accepted' },
      { userId: 'user-4', roleInMeeting: 'Member', status: 'Accepted' },
      { userId: 'user-7', roleInMeeting: 'Member', status: 'Accepted' },
    ],
    agendaItems: [
      'Evaluation of 8 applied research proposals for FY 2026-27',
      'Finalizing MoU with Haripur Technology Park',
      'Commercialization roadmap for agricultural IoT project'
    ],
    createdAt: getRelativeDateStr(-3),
    updatedAt: getRelativeDateStr(-1)
  },
  {
    id: 'mtg-103',
    title: 'CS & IT Departmental Board of Studies Prep',
    description: 'Departmental meeting to finalize course outcomes, lab equipment requisitions, and faculty workload assignment.',
    meetingType: 'Departmental',
    departmentId: 'dept-2',
    requesterId: 'user-4',
    chairId: 'user-4',
    roomId: 'room-3',
    date: getRelativeDateStr(1),
    startTime: '11:00',
    endTime: '12:30',
    mode: 'In-Person',
    status: 'Pending Approval',
    isUniversityWideEvent: false,
    participants: [
      { userId: 'user-4', roleInMeeting: 'Chair', status: 'Accepted' },
      { userId: 'user-7', roleInMeeting: 'Secretary', status: 'Accepted' },
    ],
    agendaItems: [
      'Review of Fall 2026 teaching loads',
      'Purchase of 30 GPU-enabled PCs for AI Lab',
      'Finalization of FYP defense schedule'
    ],
    createdAt: getRelativeDateStr(-1),
    updatedAt: getRelativeDateStr(-1)
  },
  {
    id: 'mtg-104',
    title: 'Syndicate Finance Committee Meeting',
    description: 'Review of annual budget allocation, campus infrastructure upgrades, and staff development funds.',
    meetingType: 'Syndicate',
    departmentId: 'dept-8',
    requesterId: 'user-2',
    chairId: 'user-2',
    roomId: 'room-5',
    date: getRelativeDateStr(2),
    startTime: '10:00',
    endTime: '13:00',
    mode: 'In-Person',
    status: 'Approved',
    isUniversityWideEvent: true,
    participants: [
      { userId: 'user-2', roleInMeeting: 'Chair', status: 'Accepted' },
      { userId: 'user-3', roleInMeeting: 'Member', status: 'Accepted' },
      { userId: 'user-5', roleInMeeting: 'Member', status: 'Accepted' },
    ],
    agendaItems: [
      'Approval of revised university budget estimates',
      'Procurement proposal for Solar Power Expansion'
    ],
    createdAt: getRelativeDateStr(-4),
    updatedAt: getRelativeDateStr(-2)
  }
];

const approvals: Approval[] = [
  {
    id: 'app-1',
    meetingId: 'mtg-101',
    approverId: 'user-2',
    approverRole: 'Registrar',
    status: 'Approved',
    comments: 'Approved as statutory university meeting. Senate hall reserved.',
    createdAt: getRelativeDateStr(-4)
  },
  {
    id: 'app-2',
    meetingId: 'mtg-102',
    approverId: 'user-6',
    approverRole: 'Room Manager',
    status: 'Approved',
    comments: 'Video conference room 102 reserved and technician assigned.',
    createdAt: getRelativeDateStr(-2)
  },
  {
    id: 'app-3',
    meetingId: 'mtg-103',
    approverId: 'user-3',
    approverRole: 'Dean',
    status: 'Pending',
    comments: '',
    createdAt: getRelativeDateStr(-1)
  }
];

const notifications: Notification[] = [
  {
    id: 'notif-1',
    userId: 'user-3',
    title: 'Pending Meeting Approval',
    message: 'Dr. Tariq Mahmood requested approval for "CS & IT Departmental Board of Studies Prep".',
    type: 'approval_required',
    isRead: false,
    meetingId: 'mtg-103',
    createdAt: getRelativeDateStr(-1)
  },
  {
    id: 'notif-2',
    userId: 'user-4',
    title: 'Meeting Confirmed',
    message: 'Your meeting "ORIC Research Grant Allocation & Industry Linkages" is approved for today at 14:00.',
    type: 'meeting_approved',
    isRead: true,
    meetingId: 'mtg-102',
    createdAt: getRelativeDateStr(-1)
  },
  {
    id: 'notif-3',
    userId: 'user-7',
    title: 'Action Item Assigned',
    message: 'You have been assigned task "Prepare BS AI Lab Specs" due in 3 days.',
    type: 'action_assigned',
    isRead: false,
    meetingId: 'mtg-101',
    createdAt: getRelativeDateStr(0)
  }
];

const minutesOfMeeting: MinutesOfMeeting[] = [
  {
    id: 'mom-1',
    meetingId: 'mtg-101',
    authorId: 'user-7',
    summary: 'The 18th Academic Council was convened under the chairmanship of Registrar UoH. The curriculum for BS Artificial Intelligence was reviewed in detail and recommended for Syndicate approval with minor adjustments in math prerequisites.',
    keyDecisions: [
      'Approved BS AI program structure starting Fall 2026 session.',
      'Endorsed QEC Annual Quality Assurance framework.',
      'Approved Terminal Exam date sheet starting 15th next month.'
    ],
    attachments: [
      { name: 'Academic_Council_18_Signed_Minutes.pdf', url: '#' }
    ],
    status: 'Published',
    createdAt: todayStr,
    publishedAt: todayStr
  }
];

const actionItems: ActionItem[] = [
  {
    id: 'act-1',
    meetingId: 'mtg-101',
    title: 'Submit finalized BS AI course outlines to HEC portal',
    assigneeId: 'user-4',
    deadline: getRelativeDateStr(5),
    priority: 'High',
    status: 'In Progress',
    notes: 'Awaiting revised prerequisites from Mathematics faculty.',
    createdAt: todayStr
  },
  {
    id: 'act-2',
    meetingId: 'mtg-101',
    title: 'Procure 30 GPU workstations for AI Research Lab',
    assigneeId: 'user-7',
    deadline: getRelativeDateStr(10),
    priority: 'High',
    status: 'Pending',
    notes: 'Draft specification document prepared.',
    createdAt: todayStr
  },
  {
    id: 'act-3',
    meetingId: 'mtg-102',
    title: 'Draft MoU for Haripur Tech Park incubation linkage',
    assigneeId: 'user-5',
    deadline: getRelativeDateStr(7),
    priority: 'Medium',
    status: 'In Progress',
    notes: 'Legal counsel reviewing clause 4.',
    createdAt: getRelativeDateStr(-1)
  }
];

const attendanceRecords: Record<string, AttendanceRecord[]> = {
  'mtg-101': [
    { userId: 'user-2', status: 'Present', remarks: 'Chaired session' },
    { userId: 'user-3', status: 'Present', remarks: 'Attended full session' },
    { userId: 'user-4', status: 'Present', remarks: 'Presented CS proposal' },
    { userId: 'user-5', status: 'Absent', remarks: 'On official duty at HEC' },
    { userId: 'user-7', status: 'Present', remarks: 'Recorded minutes' }
  ]
};

const auditLogs: AuditLog[] = [
  {
    id: 'log-1',
    timestamp: new Date().toISOString(),
    userId: 'user-2',
    userName: 'Prof. Dr. Muhammad Khan',
    action: 'Approved Meeting',
    details: 'Approved statutory meeting "UoH 18th Academic Council Session" for Senate Hall',
    ipAddress: '172.16.1.42'
  },
  {
    id: 'log-2',
    timestamp: new Date(Date.now() - 3600000).toISOString(),
    userId: 'user-4',
    userName: 'Dr. Tariq Mahmood',
    action: 'Created Meeting Request',
    details: 'Submitted request "CS & IT Departmental Board of Studies Prep"',
    ipAddress: '172.16.2.15'
  }
];

// Conflict Detection Core Logic Helper
function checkMeetingConflicts(
  roomId: string | undefined,
  date: string,
  startTime: string,
  endTime: string,
  participantUserIds: string[],
  ignoreMeetingId?: string,
  mode?: 'In-Person' | 'Online' | 'Hybrid'
): ConflictCheckResult {
  const conflicts: ConflictDetail[] = [];

  // Helper to parse time string to minutes
  const parseMinutes = (t: string) => {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
  };

  const formatTime = (mins: number) => {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  };

  const reqStart = parseMinutes(startTime);
  const reqEnd = parseMinutes(endTime);

  // 0. Validation checks (Working hours 08:00 to 18:00)
  if (reqStart < 8 * 60 || reqEnd > 18 * 60) {
    conflicts.push({
      type: 'buffer',
      title: 'Outside Campus Operational Hours',
      description: `Requested time (${startTime} - ${endTime}) is outside university operational schedule (08:00 AM - 06:00 PM).`,
      conflictingEntityName: 'Campus Schedule',
      severity: 'warning'
    });
  }

  const roomObj = roomId ? rooms.find((r) => r.id === roomId) : undefined;

  // 1. Room Inactive / Maintenance Check
  if (roomObj && !roomObj.isActive) {
    conflicts.push({
      type: 'maintenance',
      title: 'Venue Inactive / Maintenance',
      description: `Room "${roomObj.name}" is currently marked inactive or under technical maintenance.`,
      conflictingEntityName: roomObj.name,
      severity: 'critical'
    });
  }

  // 2. Capacity Check
  if (roomObj && participantUserIds.length > roomObj.capacity) {
    conflicts.push({
      type: 'capacity',
      title: 'Room Capacity Exceeded',
      description: `Invited participant count (${participantUserIds.length}) exceeds maximum room capacity (${roomObj.capacity} seats) in "${roomObj.name}".`,
      conflictingEntityName: roomObj.name,
      severity: 'warning'
    });
  }

  // 3. Facility requirement check for Hybrid / Online
  if (roomObj && (mode === 'Hybrid' || mode === 'Online')) {
    const hasVc = roomObj.facilities.some(f => f.toLowerCase().includes('vc') || f.toLowerCase().includes('video') || f.toLowerCase().includes('multimedia') || f.toLowerCase().includes('display'));
    if (!hasVc) {
      conflicts.push({
        type: 'facility',
        title: 'Missing Required VC Facilities',
        description: `Room "${roomObj.name}" lacks verified Video Conferencing / Smart Display equipment required for ${mode} sessions.`,
        conflictingEntityName: roomObj.name,
        severity: 'info'
      });
    }
  }

  // Filter existing active meetings on same date
  const activeMeetings = meetings.filter(
    (m) =>
      m.id !== ignoreMeetingId &&
      m.date === date &&
      m.status !== 'Rejected' &&
      m.status !== 'Cancelled'
  );

  for (const m of activeMeetings) {
    const mStart = parseMinutes(m.startTime);
    const mEnd = parseMinutes(m.endTime);

    // Exact or partial overlap condition: start1 < end2 && end1 > start2
    const isOverlapping = reqStart < mEnd && reqEnd > mStart;

    // Buffer check: If same room, less than 10 mins gap between back-to-back meetings
    const isBackToBackWithNoBuffer =
      roomId &&
      m.roomId === roomId &&
      !isOverlapping &&
      ((Math.abs(reqStart - mEnd) < 10) || (Math.abs(mStart - reqEnd) < 10));

    if (isOverlapping) {
      // 4. Room Double-Booking Conflict
      if (roomId && m.roomId === roomId) {
        conflicts.push({
          type: 'room',
          title: 'Room Double-Booking Conflict',
          description: `Room "${roomObj?.name || 'Selected Room'}" is already reserved for "${m.title}" during ${m.startTime} - ${m.endTime}.`,
          conflictingEntityName: roomObj?.name || 'Room',
          existingMeetingTitle: m.title,
          existingTime: `${m.startTime} - ${m.endTime}`,
          severity: 'critical',
          conflictingMeetingId: m.id
        });
      }

      // 5. Participant Schedule Conflict
      for (const pId of participantUserIds) {
        const isParticipantBooked = m.participants.some((p) => p.userId === pId);
        if (isParticipantBooked) {
          const userObj = users.find((u) => u.id === pId);
          conflicts.push({
            type: 'participant',
            title: 'Participant Schedule Conflict',
            description: `${userObj?.name} (${userObj?.role}) is already scheduled in meeting "${m.title}" during ${m.startTime} - ${m.endTime}.`,
            conflictingEntityName: userObj?.name || 'Participant',
            existingMeetingTitle: m.title,
            existingTime: `${m.startTime} - ${m.endTime}`,
            severity: 'critical',
            conflictingMeetingId: m.id
          });
        }
      }

      // 6. University Wide Event Conflict
      if (m.isUniversityWideEvent) {
        conflicts.push({
          type: 'university_event',
          title: 'University-Wide Official Event Overlap',
          description: `This slot overlaps with statutory University-Wide event "${m.title}" (${m.startTime} - ${m.endTime}).`,
          conflictingEntityName: m.title,
          existingMeetingTitle: m.title,
          existingTime: `${m.startTime} - ${m.endTime}`,
          severity: 'critical',
          conflictingMeetingId: m.id
        });
      }
    } else if (isBackToBackWithNoBuffer) {
      conflicts.push({
        type: 'buffer',
        title: 'Zero Setup Buffer Warning',
        description: `Tight back-to-back turnaround in "${roomObj?.name}". Previous meeting "${m.title}" ends at ${m.endTime} (minimum recommended buffer is 10 mins).`,
        conflictingEntityName: roomObj?.name || 'Room',
        existingMeetingTitle: m.title,
        existingTime: `${m.startTime} - ${m.endTime}`,
        severity: 'info',
        conflictingMeetingId: m.id
      });
    }
  }

  // Generate Smart Suggestions if conflict found
  const suggestions: SmartSuggestion[] = [];
  const requestedDuration = reqEnd - reqStart;

  if (conflicts.length > 0) {
    // 1. Suggest alternative available rooms for same date & time slot
    const targetCapacity = roomObj ? roomObj.capacity : participantUserIds.length || 20;

    const availableRooms = rooms.filter((r) => {
      if (!r.isActive || r.id === roomId) return false;
      // Check if this room has overlap
      const hasRoomOverlap = activeMeetings.some((m) => {
        const mStart = parseMinutes(m.startTime);
        const mEnd = parseMinutes(m.endTime);
        return m.roomId === r.id && reqStart < mEnd && reqEnd > mStart;
      });
      return !hasRoomOverlap && r.capacity >= Math.max(participantUserIds.length, targetCapacity - 10);
    });

    for (const altRoom of availableRooms.slice(0, 3)) {
      suggestions.push({
        roomId: altRoom.id,
        roomName: altRoom.name,
        capacity: altRoom.capacity,
        date: date,
        startTime: startTime,
        endTime: endTime,
        reason: `Room "${altRoom.name}" (Capacity: ${altRoom.capacity}) in ${altRoom.building} is 100% free at ${startTime} - ${endTime}`
      });
    }

    // 2. Suggest alternative time slot for same room if requested
    if (roomObj && roomObj.isActive) {
      const candidateStartHours = [9, 11, 14, 15, 16];
      for (const startH of candidateStartHours) {
        const slotStart = startH * 60;
        const slotEnd = slotStart + requestedDuration;
        if (slotEnd <= 17 * 60 && (slotStart !== reqStart)) {
          const hasOverlap = activeMeetings.some((m) => {
            const mStart = parseMinutes(m.startTime);
            const mEnd = parseMinutes(m.endTime);
            // Check room overlap or participant overlap
            const roomColl = m.roomId === roomId && slotStart < mEnd && slotEnd > mStart;
            const partColl = m.participants.some(p => participantUserIds.includes(p.userId)) && slotStart < mEnd && slotEnd > mStart;
            return roomColl || partColl;
          });

          if (!hasOverlap) {
            const altStartStr = formatTime(slotStart);
            const altEndStr = formatTime(slotEnd);
            suggestions.push({
              roomId: roomObj.id,
              roomName: roomObj.name,
              capacity: roomObj.capacity,
              date: date,
              startTime: altStartStr,
              endTime: altEndStr,
              reason: `Shift slot to ${altStartStr} - ${altEndStr} in "${roomObj.name}" (Zero conflicts across all participants)`
            });
            if (suggestions.length >= 4) break;
          }
        }
      }
    }
  }

  // Calculate Participant Availability Slots
  const participantAvailability: ParticipantFreeSlot[] = participantUserIds.map((pId) => {
    const userObj = users.find((u) => u.id === pId);
    const userMeetings = activeMeetings.filter((m) => m.participants.some((p) => p.userId === pId));
    
    const busyIntervals = userMeetings.map((m) => ({
      start: m.startTime,
      end: m.endTime,
      meetingTitle: m.title
    }));

    return {
      userId: pId,
      userName: userObj?.name || 'User',
      busyIntervals,
      freeIntervals: [
        { start: '09:00', end: '17:00' } // simplified default work day window
      ]
    };
  });

  return {
    hasConflict: conflicts.some(c => c.severity === 'critical' || c.severity === 'warning'),
    conflicts,
    suggestions,
    participantAvailability
  };
}

// Global System Clash Scanner Function
function scanAllSystemClashes(): GlobalClashItem[] {
  const globalClashes: GlobalClashItem[] = [];
  const processedPairs = new Set<string>();

  const activeMeetings = meetings.filter(
    (m) => m.status !== 'Rejected' && m.status !== 'Cancelled'
  );

  const parseMinutes = (t: string) => {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
  };

  for (let i = 0; i < activeMeetings.length; i++) {
    const m1 = activeMeetings[i];
    const m1Start = parseMinutes(m1.startTime);
    const m1End = parseMinutes(m1.endTime);

    // 1. Capacity check for m1
    if (m1.roomId) {
      const room = rooms.find((r) => r.id === m1.roomId);
      if (room && m1.participants.length > room.capacity) {
        globalClashes.push({
          id: `clash-cap-${m1.id}`,
          meeting1: m1,
          clashType: 'capacity',
          severity: 'warning',
          title: `Room Overcapacity in ${room.name}`,
          description: `Meeting "${m1.title}" has ${m1.participants.length} participants but room "${room.name}" only holds ${room.capacity} seats.`,
          conflictingEntityName: room.name,
          date: m1.date,
          timeWindow: `${m1.startTime} - ${m1.endTime}`,
          suggestions: rooms
            .filter((r) => r.capacity >= m1.participants.length && r.isActive && r.id !== room.id)
            .slice(0, 2)
            .map((alt) => ({
              roomId: alt.id,
              roomName: alt.name,
              capacity: alt.capacity,
              date: m1.date,
              startTime: m1.startTime,
              endTime: m1.endTime,
              reason: `Upgrade to larger hall "${alt.name}" (Capacity: ${alt.capacity})`
            }))
        });
      }
    }

    for (let j = i + 1; j < activeMeetings.length; j++) {
      const m2 = activeMeetings[j];
      if (m1.date !== m2.date) continue;

      const pairKey = [m1.id, m2.id].sort().join(':::');
      if (processedPairs.has(pairKey)) continue;

      const m2Start = parseMinutes(m2.startTime);
      const m2End = parseMinutes(m2.endTime);

      const isOverlapping = m1Start < m2End && m1End > m2Start;

      if (isOverlapping) {
        // Room Double-Booking Clash
        if (m1.roomId && m2.roomId && m1.roomId === m2.roomId) {
          processedPairs.add(pairKey);
          const room = rooms.find((r) => r.id === m1.roomId);
          const conflictRes = checkMeetingConflicts(
            m2.roomId,
            m2.date,
            m2.startTime,
            m2.endTime,
            m2.participants.map((p) => p.userId),
            m2.id
          );

          globalClashes.push({
            id: `clash-room-${m1.id}-${m2.id}`,
            meeting1: m1,
            meeting2: m2,
            clashType: 'room',
            severity: 'critical',
            title: `Room Double-Booking: ${room?.name || 'Venue'}`,
            description: `Simultaneous room booking between "${m1.title}" (${m1.startTime}-${m1.endTime}) and "${m2.title}" (${m2.startTime}-${m2.endTime}).`,
            conflictingEntityName: room?.name || 'Room',
            date: m1.date,
            timeWindow: `${Math.max(m1Start, m2Start) === m1Start ? m1.startTime : m2.startTime} - ${Math.min(m1End, m2End) === m1End ? m1.endTime : m2.endTime}`,
            suggestions: conflictRes.suggestions
          });
        }

        // Participant Overlap Clash
        const sharedParticipants = m1.participants.filter((p1) =>
          m2.participants.some((p2) => p2.userId === p1.userId)
        );

        if (sharedParticipants.length > 0) {
          processedPairs.add(pairKey);
          const userNames = sharedParticipants
            .map((p) => users.find((u) => u.id === p.userId)?.name)
            .filter(Boolean)
            .join(', ');

          globalClashes.push({
            id: `clash-part-${m1.id}-${m2.id}`,
            meeting1: m1,
            meeting2: m2,
            clashType: 'participant',
            severity: 'critical',
            title: `Participant Schedule Conflict (${sharedParticipants.length} member${sharedParticipants.length > 1 ? 's' : ''})`,
            description: `Personnel [${userNames}] assigned to both "${m1.title}" (${m1.startTime}-${m1.endTime}) and "${m2.title}" (${m2.startTime}-${m2.endTime}).`,
            conflictingEntityName: userNames,
            date: m1.date,
            timeWindow: `${m1.startTime} - ${m1.endTime}`,
            suggestions: [
              {
                roomId: m2.roomId || 'room-1',
                roomName: rooms.find(r => r.id === m2.roomId)?.name || 'Current Room',
                capacity: 25,
                date: m2.date,
                startTime: '15:30',
                endTime: '17:00',
                reason: `Reschedule "${m2.title}" to afternoon slot (15:30 - 17:00) when all participants are free.`
              }
            ]
          });
        }

        // Statutory Event Overlap
        if ((m1.isUniversityWideEvent || m2.isUniversityWideEvent) && !processedPairs.has(pairKey)) {
          processedPairs.add(pairKey);
          const statutoryMeeting = m1.isUniversityWideEvent ? m1 : m2;
          const routineMeeting = m1.isUniversityWideEvent ? m2 : m1;

          globalClashes.push({
            id: `clash-stat-${m1.id}-${m2.id}`,
            meeting1: statutoryMeeting,
            meeting2: routineMeeting,
            clashType: 'university_event',
            severity: 'critical',
            title: `Statutory University-Wide Event Lockout`,
            description: `Routine meeting "${routineMeeting.title}" overlaps with mandatory campus event "${statutoryMeeting.title}".`,
            conflictingEntityName: statutoryMeeting.title,
            date: m1.date,
            timeWindow: `${statutoryMeeting.startTime} - ${statutoryMeeting.endTime}`,
            suggestions: [
              {
                roomId: routineMeeting.roomId || 'room-1',
                roomName: rooms.find(r => r.id === routineMeeting.roomId)?.name || 'Venue',
                capacity: 20,
                date: routineMeeting.date,
                startTime: '16:00',
                endTime: '17:30',
                reason: `Move routine meeting "${routineMeeting.title}" to post-statutory session (16:00 - 17:30).`
              }
            ]
          });
        }
      }
    }
  }

  return globalClashes;
}

// ---------------- REST API ENDPOINTS ----------------

// System Stats
app.get('/api/stats', (req, res) => {
  const totalMeetings = meetings.length;
  const pendingApprovals = meetings.filter((m) => m.status === 'Pending Approval').length;
  const approvedMeetings = meetings.filter((m) => m.status === 'Approved').length;
  const completedMeetings = meetings.filter((m) => m.status === 'Completed').length;
  const totalRooms = rooms.length;
  
  // Basic utilization rate calculation
  const todayMeetingsCount = meetings.filter((m) => m.date === todayStr && m.status === 'Approved').length;
  const roomUtilizationRate = Math.min(100, Math.round((todayMeetingsCount / (rooms.length * 2)) * 100));

  const activeActionItems = actionItems.filter((a) => a.status !== 'Completed').length;

  const stats: SystemStats = {
    totalMeetings,
    pendingApprovals,
    approvedMeetings,
    completedMeetings,
    totalRooms,
    roomUtilizationRate: roomUtilizationRate || 35,
    activeActionItems
  };

  res.json(stats);
});

// Users & Departments
app.get('/api/users', (req, res) => res.json(users));
app.get('/api/departments', (req, res) => res.json(departments));

// Rooms
app.get('/api/rooms', (req, res) => res.json(rooms));
app.post('/api/rooms', (req, res) => {
  const newRoom: Room = {
    id: `room-${Date.now()}`,
    ...req.body
  };
  rooms.push(newRoom);
  res.status(201).json(newRoom);
});

// Conflict Detection Endpoint
app.post('/api/meetings/check-conflict', (req, res) => {
  const { roomId, date, startTime, endTime, participantUserIds, ignoreMeetingId, mode } = req.body;
  const result = checkMeetingConflicts(
    roomId,
    date,
    startTime,
    endTime,
    participantUserIds || [],
    ignoreMeetingId,
    mode
  );
  res.json(result);
});

// Global Clash Audit & Scanner Endpoint
app.get('/api/clashes/scan', (req, res) => {
  const clashes = scanAllSystemClashes();
  res.json(clashes);
});

// 1-Click Clash Auto-Resolver
app.post('/api/clashes/auto-resolve', (req, res) => {
  const { meetingId, newRoomId, newDate, newStartTime, newEndTime } = req.body;
  const meeting = meetings.find((m) => m.id === meetingId);
  if (!meeting) {
    return res.status(404).json({ error: 'Meeting not found' });
  }

  if (newRoomId) meeting.roomId = newRoomId;
  if (newDate) meeting.date = newDate;
  if (newStartTime) meeting.startTime = newStartTime;
  if (newEndTime) meeting.endTime = newEndTime;
  meeting.updatedAt = new Date().toISOString().split('T')[0];

  // Add audit log entry
  auditLogs.unshift({
    id: `log-${Date.now()}`,
    timestamp: new Date().toISOString(),
    userId: 'system-resolver',
    userName: 'UoH Conflict Engine',
    action: 'Auto-Resolved Clash',
    details: `Auto-adjusted schedule for "${meeting.title}" to ${meeting.date} ${meeting.startTime}-${meeting.endTime} in room ${meeting.roomId}`,
    ipAddress: '127.0.0.1'
  });

  res.json({ success: true, updatedMeeting: meeting });
});

// Meetings CRUD
app.get('/api/meetings', (req, res) => {
  const { status, departmentId, roomId, date } = req.query;
  let result = [...meetings];

  if (status) result = result.filter((m) => m.status === status);
  if (departmentId) result = result.filter((m) => m.departmentId === departmentId);
  if (roomId) result = result.filter((m) => m.roomId === roomId);
  if (date) result = result.filter((m) => m.date === date);

  res.json(result);
});

app.get('/api/meetings/:id', (req, res) => {
  const meeting = meetings.find((m) => m.id === req.params.id);
  if (!meeting) return res.status(404).json({ error: 'Meeting not found' });
  res.json(meeting);
});

app.post('/api/meetings', (req, res) => {
  const {
    title,
    description,
    meetingType,
    departmentId,
    requesterId,
    chairId,
    roomId,
    date,
    startTime,
    endTime,
    mode,
    onlineLink,
    participantUserIds,
    agendaItems,
    isUniversityWideEvent
  } = req.body;

  // Perform strict conflict check before saving
  const conflictResult = checkMeetingConflicts(
    roomId,
    date,
    startTime,
    endTime,
    participantUserIds || []
  );

  // Determine initial status & approver based on rules (BR-001, BR-011, BR-012, Mentor Decisions):
  const selectedRoom = rooms.find((r) => r.id === roomId);
  const requiresRoomApproval = !!selectedRoom?.requiresApproval;
  const requesterUser = users.find((u) => u.id === requesterId);

  const isSuperAdminOrRegistrar = requesterUser?.role === 'Super Admin' || requesterUser?.role === 'Registrar';
  const isHODorDeanOrDirector = requesterUser?.role === 'HOD' || requesterUser?.role === 'Dean' || requesterUser?.role === 'Director' || requesterUser?.role === 'Office Admin';
  const isInternalType = ['Departmental', 'ORIC / Research', 'Committee / Office', 'Routine Admin'].includes(meetingType);
  const isStatutoryType = ['Academic Council', 'Syndicate', 'Senate'].includes(meetingType);

  let isPending = false;
  let targetApproverId = 'user-2'; // Default Registrar
  let targetApproverRole = 'Registrar';

  if (isSuperAdminOrRegistrar) {
    // Super Admin / Registrar auto-approves
    isPending = false;
  } else if (isHODorDeanOrDirector && isInternalType && !requiresRoomApproval) {
    // HOD/Dean/Director creating internal meeting in standard room: AUTO-APPROVED (BR-001 & BR-012)
    isPending = false;
  } else if (requiresRoomApproval) {
    // Room requires room manager authorization
    isPending = true;
    targetApproverId = 'user-6'; // Room Manager
    targetApproverRole = 'Room Manager';
  } else if (isStatutoryType || meetingType === 'University Wide') {
    // University-wide or statutory meeting requires Registrar / Super Admin approval
    isPending = true;
    targetApproverId = 'user-2'; // Registrar
    targetApproverRole = 'Registrar';
  } else {
    // Faculty/Staff request or internal request needing departmental HOD or Dean approval
    isPending = true;
    const deptObj = departments.find((d) => d.id === departmentId);
    if (deptObj?.code === 'CSIT') {
      targetApproverId = 'user-4'; // Dr. Tariq Mahmood (HOD CS&IT)
      targetApproverRole = 'Head of Department';
    } else {
      targetApproverId = 'user-3'; // Dean, Faculty of Sciences
      targetApproverRole = 'Dean';
    }
  }

  const newMeeting: Meeting = {
    id: `mtg-${Date.now()}`,
    title,
    description: description || '',
    meetingType,
    departmentId,
    requesterId,
    chairId,
    roomId,
    date,
    startTime,
    endTime,
    mode: mode || 'In-Person',
    onlineLink,
    status: isPending ? 'Pending Approval' : 'Approved',
    isUniversityWideEvent: !!isUniversityWideEvent,
    participants: (participantUserIds || [chairId]).map((uId: string) => ({
      userId: uId,
      roleInMeeting: uId === chairId ? 'Chair' : 'Member',
      status: 'Invited'
    })),
    agendaItems: agendaItems || [],
    createdAt: new Date().toISOString().split('T')[0],
    updatedAt: new Date().toISOString().split('T')[0]
  };

  meetings.push(newMeeting);

  // If pending, create approval task assigned strictly to the relevant authority
  if (newMeeting.status === 'Pending Approval') {
    const newApproval: Approval = {
      id: `app-${Date.now()}`,
      meetingId: newMeeting.id,
      approverId: targetApproverId,
      approverRole: targetApproverRole,
      status: 'Pending',
      createdAt: new Date().toISOString()
    };
    approvals.push(newApproval);

    // Notify assigned approver only
    notifications.push({
      id: `notif-${Date.now()}`,
      userId: targetApproverId,
      title: 'New Meeting Approval Request',
      message: `Meeting request "${newMeeting.title}" submitted by ${requesterUser?.name || 'requester'} requires your review as ${targetApproverRole}.`,
      type: 'approval_required',
      isRead: false,
      meetingId: newMeeting.id,
      createdAt: new Date().toISOString()
    });
  }

  // Audit Log
  const requester = users.find((u) => u.id === requesterId);
  auditLogs.unshift({
    id: `log-${Date.now()}`,
    timestamp: new Date().toISOString(),
    userId: requesterId || 'system',
    userName: requester ? requester.name : 'User',
    action: 'Submitted Meeting Request',
    details: `Submitted "${newMeeting.title}" for ${newMeeting.date} (${newMeeting.startTime}-${newMeeting.endTime})`,
    ipAddress: '172.16.0.1'
  });

  res.status(201).json({
    meeting: newMeeting,
    conflictResult
  });
});

// Update Meeting Status (Approve / Reject / Cancel / Reschedule)
app.patch('/api/meetings/:id/status', (req, res) => {
  const { status, comments, approverId } = req.body;
  const meeting = meetings.find((m) => m.id === req.params.id);
  
  if (!meeting) return res.status(404).json({ error: 'Meeting not found' });

  meeting.status = status;
  meeting.updatedAt = new Date().toISOString().split('T')[0];

  // Record approval record
  const approvalObj = approvals.find((a) => a.meetingId === meeting.id);
  if (approvalObj) {
    approvalObj.status = status === 'Approved' ? 'Approved' : 'Rejected';
    approvalObj.comments = comments || '';
  }

  // Send notification to requester
  notifications.unshift({
    id: `notif-${Date.now()}`,
    userId: meeting.requesterId,
    title: `Meeting Request ${status}`,
    message: `Your meeting request "${meeting.title}" has been ${status.toLowerCase()}.${comments ? ` Note: ${comments}` : ''}`,
    type: status === 'Approved' ? 'meeting_approved' : 'meeting_rejected',
    isRead: false,
    meetingId: meeting.id,
    createdAt: new Date().toISOString()
  });

  // Audit log
  const approver = users.find((u) => u.id === approverId);
  auditLogs.unshift({
    id: `log-${Date.now()}`,
    timestamp: new Date().toISOString(),
    userId: approverId || 'user-2',
    userName: approver ? approver.name : 'Authority',
    action: `${status} Meeting`,
    details: `${status} meeting request "${meeting.title}". ${comments || ''}`,
    ipAddress: '172.16.1.10'
  });

  res.json(meeting);
});

// Approvals Queue
app.get('/api/approvals', (req, res) => {
  const { userId, role, departmentId } = req.query as { userId?: string; role?: string; departmentId?: string };

  let pendingMeetings = meetings.filter((m) => m.status === 'Pending Approval');

  if (userId && role) {
    const userObj = users.find((u) => u.id === userId);
    
    pendingMeetings = pendingMeetings.filter((m) => {
      const approvalRecord = approvals.find((a) => a.meetingId === m.id);

      // Explicitly assigned to this user ID
      if (approvalRecord && approvalRecord.approverId === userId) {
        return true;
      }

      // Room Manager: sees room approval requests
      if (role === 'Room Manager') {
        const room = rooms.find((r) => r.id === m.roomId);
        return room?.requiresApproval === true || approvalRecord?.approverRole === 'Room Manager';
      }

      // Registrar / Super Admin: sees statutory / university-wide approval requests
      if (role === 'Super Admin' || role === 'Registrar') {
        const isStatutory = ['Academic Council', 'Syndicate', 'Senate'].includes(m.meetingType) || m.isUniversityWideEvent;
        return isStatutory || approvalRecord?.approverRole === 'Registrar';
      }

      // Dean: sees requests in their faculty / assigned to Dean
      if (role === 'Dean') {
        return approvalRecord?.approverRole === 'Dean' || m.departmentId === departmentId;
      }

      // HOD / Director / Office Admin: sees requests for THEIR department only
      if (role === 'HOD' || role === 'Director' || role === 'Office Admin') {
        const targetDept = departmentId || userObj?.departmentId;
        return m.departmentId === targetDept || approvalRecord?.approverRole === 'Head of Department';
      }

      // Faculty / Staff: no approval queue authority
      return false;
    });
  }

  const result = pendingMeetings.map((m) => {
    const approvalRecord = approvals.find((a) => a.meetingId === m.id);
    const requester = users.find((u) => u.id === m.requesterId);
    const room = rooms.find((r) => r.id === m.roomId);
    
    // Conflict check preview for approvers
    const conflicts = checkMeetingConflicts(
      m.roomId,
      m.date,
      m.startTime,
      m.endTime,
      m.participants.map((p) => p.userId),
      m.id
    );

    return {
      meeting: m,
      approvalRecord,
      requester,
      room,
      conflictAnalysis: conflicts
    };
  });

  res.json(result);
});

// Minutes of Meeting
app.get('/api/minutes/:meetingId', (req, res) => {
  const mom = minutesOfMeeting.find((m) => m.meetingId === req.params.meetingId);
  res.json(mom || null);
});

app.post('/api/minutes', (req, res) => {
  const { meetingId, authorId, summary, keyDecisions, attachments } = req.body;
  const existingIndex = minutesOfMeeting.findIndex((m) => m.meetingId === meetingId);
  
  const newMom: MinutesOfMeeting = {
    id: existingIndex >= 0 ? minutesOfMeeting[existingIndex].id : `mom-${Date.now()}`,
    meetingId,
    authorId,
    summary,
    keyDecisions: keyDecisions || [],
    attachments: attachments || [],
    status: 'Published',
    createdAt: new Date().toISOString().split('T')[0],
    publishedAt: new Date().toISOString().split('T')[0]
  };

  if (existingIndex >= 0) {
    minutesOfMeeting[existingIndex] = newMom;
  } else {
    minutesOfMeeting.push(newMom);
  }

  // Mark meeting as completed
  const meeting = meetings.find((m) => m.id === meetingId);
  if (meeting) {
    meeting.status = 'Completed';
  }

  res.status(201).json(newMom);
});

// Attendance API
app.get('/api/attendance/:meetingId', (req, res) => {
  const records = attendanceRecords[req.params.meetingId] || [];
  res.json(records);
});

app.post('/api/attendance/:meetingId', (req, res) => {
  const { records } = req.body;
  attendanceRecords[req.params.meetingId] = records;
  
  // Update meeting participant attended field
  const meeting = meetings.find((m) => m.id === req.params.meetingId);
  if (meeting && Array.isArray(records)) {
    records.forEach((rec: AttendanceRecord) => {
      const p = meeting.participants.find((pt) => pt.userId === rec.userId);
      if (p) {
        p.attended = rec.status === 'Present';
      }
    });
  }

  res.json({ success: true, count: records.length });
});

// Action Items
app.get('/api/action-items', (req, res) => {
  const { meetingId, assigneeId } = req.query;
  let result = [...actionItems];

  if (meetingId) result = result.filter((a) => a.meetingId === meetingId);
  if (assigneeId) result = result.filter((a) => a.assigneeId === assigneeId);

  res.json(result);
});

app.post('/api/action-items', (req, res) => {
  const newAction: ActionItem = {
    id: `act-${Date.now()}`,
    meetingId: req.body.meetingId,
    title: req.body.title,
    assigneeId: req.body.assigneeId,
    deadline: req.body.deadline,
    priority: req.body.priority || 'Medium',
    status: 'Pending',
    notes: req.body.notes || '',
    createdAt: new Date().toISOString().split('T')[0]
  };

  actionItems.push(newAction);

  // Notify Assignee
  notifications.unshift({
    id: `notif-${Date.now()}`,
    userId: newAction.assigneeId,
    title: 'New Action Item Assigned',
    message: `Task: "${newAction.title}" due by ${newAction.deadline}`,
    type: 'action_assigned',
    isRead: false,
    meetingId: newAction.meetingId,
    createdAt: new Date().toISOString()
  });

  res.status(201).json(newAction);
});

app.patch('/api/action-items/:id/status', (req, res) => {
  const { status, notes } = req.body;
  const item = actionItems.find((a) => a.id === req.params.id);
  if (!item) return res.status(404).json({ error: 'Action item not found' });

  item.status = status;
  if (notes) item.notes = notes;

  res.json(item);
});

// Notifications
app.get('/api/notifications/:userId', (req, res) => {
  const userNotifs = notifications.filter((n) => n.userId === req.params.userId);
  res.json(userNotifs);
});

app.patch('/api/notifications/:id/read', (req, res) => {
  const notif = notifications.find((n) => n.id === req.params.id);
  if (notif) notif.isRead = true;
  res.json({ success: true });
});

// Audit Logs
app.get('/api/audit-logs', (req, res) => res.json(auditLogs));

// Global Error Handler
app.use((err: any, req: express.Request, res: express.Response, next: express.NextFunction) => {
  console.error('Express API Error:', err);
  res.status(500).json({ error: err?.message || 'Internal Server Error' });
});

// ---------------- VITE MIDDLEWARE SETUP ----------------
async function startServer() {
  if (process.env.NODE_ENV !== 'production' && !process.env.VERCEL) {
    try {
      const vite = await createViteServer({
        server: { middlewareMode: true },
        appType: 'spa',
      });
      app.use(vite.middlewares);
    } catch (e) {
      console.error('Failed to attach Vite middleware:', e);
    }
  } else if (!process.env.VERCEL) {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  if (!process.env.VERCEL) {
    app.listen(PORT, '0.0.0.0', () => {
      console.log(`UoH Meeting Management System running on http://localhost:${PORT}`);
    });
  }
}

startServer();

export default app;
