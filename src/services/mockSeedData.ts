import { 
  Department, User, Room, Meeting, Approval, Notification, 
  MinutesOfMeeting, ActionItem, AuditLog, SystemStats 
} from '../types/index';

const today = new Date();
const todayStr = today.toISOString().split('T')[0];

const getRelativeDateStr = (days: number) => {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().split('T')[0];
};

export const seedDepartments: Department[] = [
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

export const seedUsers: User[] = [
  {
    id: 'user-1',
    name: 'Dr. Shafiq Ahmed',
    email: 'shafiq.ahmed@uoh.edu.pk',
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

export const seedRooms: Room[] = [
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

export const seedMeetings: Meeting[] = [
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

export const seedStats: SystemStats = {
  totalMeetings: seedMeetings.length,
  pendingApprovals: seedMeetings.filter((m) => m.status === 'Pending Approval').length,
  approvedMeetings: seedMeetings.filter((m) => m.status === 'Approved').length,
  completedMeetings: seedMeetings.filter((m) => m.status === 'Completed').length,
  totalRooms: seedRooms.length,
  roomUtilizationRate: 45,
  activeActionItems: 3
};
