import React, { useState, useEffect } from 'react';
import { Meeting, Room, User, MinutesOfMeeting, ActionItem, AttendanceRecord } from '../types/index';
import { 
  fetchMinutes, saveMinutes, fetchAttendance, saveAttendance, 
  fetchActionItems, createActionItem 
} from '../services/api';
import { 
  X, FileText, CheckCircle2, Plus, UserCheck, ListTodo 
} from 'lucide-react';

interface MeetingDetailModalProps {
  meeting: Meeting | null;
  onClose: () => void;
  users: User[];
  rooms: Room[];
  currentUser: User;
  onMeetingUpdated: () => void;
}

export const MeetingDetailModal: React.FC<MeetingDetailModalProps> = ({
  meeting,
  onClose,
  users,
  rooms,
  currentUser,
  onMeetingUpdated
}) => {
  if (!meeting) return null;

  const room = rooms.find((r) => r.id === meeting.roomId);
  const chair = users.find((u) => u.id === meeting.chairId);
  const requester = users.find((u) => u.id === meeting.requesterId);

  const [activeTab, setActiveTab] = useState<'overview' | 'attendance' | 'mom' | 'action-items'>('overview');

  // Attendance State
  const [attendance, setAttendance] = useState<AttendanceRecord[]>([]);
  const [savingAttendance, setSavingAttendance] = useState(false);

  // Minutes State
  const [mom, setMom] = useState<MinutesOfMeeting | null>(null);
  const [momSummary, setMomSummary] = useState('');
  const [keyDecisions, setKeyDecisions] = useState<string[]>([]);
  const [newDecisionInput, setNewDecisionInput] = useState('');
  const [savingMom, setSavingMom] = useState(false);

  // Action Items State
  const [meetingActionItems, setMeetingActionItems] = useState<ActionItem[]>([]);
  const [newActionTitle, setNewActionTitle] = useState('');
  const [newActionAssignee, setNewActionAssignee] = useState(users[0]?.id || '');
  const [newActionDeadline, setNewActionDeadline] = useState(
    new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0]
  );
  const [newActionPriority, setNewActionPriority] = useState<'High' | 'Medium' | 'Low'>('High');

  useEffect(() => {
    loadMeetingData();
  }, [meeting.id]);

  const loadMeetingData = async () => {
    try {
      // 1. Attendance
      const attData = await fetchAttendance(meeting.id);
      if (attData && attData.length > 0) {
        setAttendance(attData);
      } else {
        // Initialize from participants list
        setAttendance(
          meeting.participants.map((p) => ({
            userId: p.userId,
            status: p.attended ? 'Present' : 'Present'
          }))
        );
      }

      // 2. MoM
      const momData = await fetchMinutes(meeting.id);
      if (momData) {
        setMom(momData);
        setMomSummary(momData.summary);
        setKeyDecisions(momData.keyDecisions);
      } else {
        setMomSummary('');
        setKeyDecisions([]);
      }

      // 3. Action Items
      const actData = await fetchActionItems({ meetingId: meeting.id });
      setMeetingActionItems(actData);
    } catch (e) {
      console.error(e);
    }
  };

  const handleSaveAttendance = async () => {
    setSavingAttendance(true);
    try {
      await saveAttendance(meeting.id, attendance);
      onMeetingUpdated();
    } catch (e) {
      console.error(e);
    } finally {
      setSavingAttendance(false);
    }
  };

  const toggleAttendanceStatus = (userId: string, status: 'Present' | 'Absent' | 'Excused') => {
    setAttendance((prev) =>
      prev.map((a) => (a.userId === userId ? { ...a, status } : a))
    );
  };

  const handleSaveMinutes = async () => {
    setSavingMom(true);
    try {
      const saved = await saveMinutes({
        meetingId: meeting.id,
        authorId: currentUser.id,
        summary: momSummary,
        keyDecisions,
        status: 'Published'
      });
      setMom(saved);
      onMeetingUpdated();
    } catch (e) {
      console.error(e);
    } finally {
      setSavingMom(false);
    }
  };

  const handleAddDecision = () => {
    if (newDecisionInput.trim()) {
      setKeyDecisions([...keyDecisions, newDecisionInput.trim()]);
      setNewDecisionInput('');
    }
  };

  const handleCreateAction = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newActionTitle.trim()) return;

    try {
      const created = await createActionItem({
        meetingId: meeting.id,
        title: newActionTitle,
        assigneeId: newActionAssignee,
        deadline: newActionDeadline,
        priority: newActionPriority
      });
      setMeetingActionItems([...meetingActionItems, created]);
      setNewActionTitle('');
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
      <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl max-h-[92vh] overflow-y-auto shadow-2xl text-slate-900 flex flex-col my-auto">
        
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
          <div>
            <div className="flex items-center space-x-2">
              <span className="text-base font-bold text-slate-900">{meeting.title}</span>
              <span className={`px-2 py-0.5 rounded-lg text-[10px] font-bold ${
                meeting.status === 'Approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                meeting.status === 'Completed' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' :
                'bg-slate-100 text-slate-700'
              }`}>
                {meeting.status}
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5 font-medium">
              {meeting.date} • {meeting.startTime} - {meeting.endTime} • {room?.name || 'Custom Venue'}
            </p>
          </div>

          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-700 p-1.5 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tab Navigation Header */}
        <div className="flex border-b border-slate-100 bg-slate-50/50 px-6 text-xs font-semibold overflow-x-auto">
          {[
            { id: 'overview', label: 'Overview & Agendas', icon: FileText },
            { id: 'attendance', label: 'Attendance Logger', icon: UserCheck },
            { id: 'mom', label: 'Minutes of Meeting (MoM)', icon: CheckCircle2 },
            { id: 'action-items', label: 'Action Items Tracker', icon: ListTodo }
          ].map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`py-3 px-4 border-b-2 font-bold flex items-center space-x-2 transition-colors cursor-pointer whitespace-nowrap ${
                  isActive 
                    ? 'border-indigo-600 text-indigo-700 bg-white' 
                    : 'border-transparent text-slate-500 hover:text-slate-800'
                }`}
              >
                <Icon className="w-3.5 h-3.5 text-indigo-600" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>

        {/* Modal Body Tabs */}
        <div className="p-6 text-xs space-y-4 flex-1">
          
          {/* TAB 1: OVERVIEW */}
          {activeTab === 'overview' && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                <div>
                  <div className="text-[10px] text-slate-500 font-medium">Chair Officer</div>
                  <div className="font-bold text-slate-900 mt-0.5">{chair?.name || 'N/A'}</div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500 font-medium">Requested By</div>
                  <div className="font-bold text-slate-900 mt-0.5">{requester?.name || 'N/A'}</div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500 font-medium">Meeting Mode</div>
                  <div className="font-bold text-indigo-700 mt-0.5">{meeting.mode}</div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500 font-medium">Category</div>
                  <div className="font-bold text-amber-800 mt-0.5">{meeting.meetingType}</div>
                </div>
              </div>

              {meeting.onlineLink && (
                <div className="bg-slate-50 p-3 rounded-2xl flex items-center justify-between border border-slate-200">
                  <span className="text-slate-700 font-medium">Online Video Conference Link:</span>
                  <a
                    href={meeting.onlineLink}
                    target="_blank"
                    rel="noreferrer"
                    className="text-indigo-600 hover:underline font-mono font-bold"
                  >
                    {meeting.onlineLink}
                  </a>
                </div>
              )}

              {/* Description */}
              <div className="space-y-1">
                <h4 className="font-bold text-slate-900">Meeting Description</h4>
                <p className="text-slate-700 leading-relaxed bg-slate-50 p-3.5 rounded-2xl border border-slate-200 font-medium">
                  {meeting.description || 'No detailed description provided.'}
                </p>
              </div>

              {/* Agendas */}
              <div className="space-y-2">
                <h4 className="font-bold text-slate-900">Official Agendas</h4>
                <div className="space-y-1">
                  {meeting.agendaItems.map((ag, i) => (
                    <div key={i} className="bg-slate-50 p-2.5 rounded-xl border border-slate-200 font-mono text-slate-800 font-medium">
                      {i + 1}. {ag}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: ATTENDANCE */}
          {activeTab === 'attendance' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="font-bold text-slate-900">Mark Participant Attendance</h4>
                <button
                  onClick={handleSaveAttendance}
                  disabled={savingAttendance}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-xl font-bold shadow-sm transition-colors cursor-pointer"
                >
                  {savingAttendance ? 'Saving...' : 'Save Attendance'}
                </button>
              </div>

              <div className="divide-y divide-slate-100 bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
                {meeting.participants.map((p) => {
                  const userObj = users.find((u) => u.id === p.userId);
                  const attRecord = attendance.find((a) => a.userId === p.userId);
                  const currentStatus = attRecord?.status || 'Present';

                  return (
                    <div key={p.userId} className="p-3 flex items-center justify-between hover:bg-slate-100/60">
                      <div className="flex items-center space-x-3">
                        <img
                          src={userObj?.avatar}
                          alt={userObj?.name}
                          className="w-8 h-8 rounded-full object-cover border border-slate-200"
                        />
                        <div>
                          <div className="font-bold text-slate-900">{userObj?.name}</div>
                          <div className="text-[10px] text-slate-500 font-medium">{userObj?.designation} • {p.roleInMeeting}</div>
                        </div>
                      </div>

                      <div className="flex items-center space-x-1">
                        {(['Present', 'Absent', 'Excused'] as const).map((st) => (
                          <button
                            key={st}
                            type="button"
                            onClick={() => toggleAttendanceStatus(p.userId, st)}
                            className={`px-2.5 py-1 rounded-lg text-[10px] font-bold transition-colors cursor-pointer ${
                              currentStatus === st
                                ? st === 'Present' ? 'bg-emerald-600 text-white' :
                                  st === 'Absent' ? 'bg-rose-600 text-white' :
                                  'bg-amber-600 text-white'
                                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'
                            }`}
                          >
                            {st}
                          </button>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* TAB 3: MINUTES OF MEETING (MoM) */}
          {activeTab === 'mom' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="font-bold text-slate-900">Official Minutes of Meeting (MoM)</h4>
                  <p className="text-[10px] text-slate-500 font-medium">Record discussion summaries and key statutory decisions.</p>
                </div>
                <button
                  onClick={handleSaveMinutes}
                  disabled={savingMom}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-xl font-bold shadow-sm transition-colors cursor-pointer"
                >
                  {savingMom ? 'Publishing...' : 'Publish MoM'}
                </button>
              </div>

              <div className="space-y-1">
                <label className="block font-bold text-slate-700">Executive Discussion Summary</label>
                <textarea
                  rows={4}
                  placeholder="Record summary of deliberations, remarks by chair, and unanimous consensus..."
                  value={momSummary}
                  onChange={(e) => setMomSummary(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div className="space-y-2">
                <label className="block font-bold text-slate-700">Approved Key Decisions</label>
                <div className="flex space-x-2">
                  <input
                    type="text"
                    placeholder="Enter explicit decision/resolution..."
                    value={newDecisionInput}
                    onChange={(e) => setNewDecisionInput(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        handleAddDecision();
                      }
                    }}
                    className="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  />
                  <button
                    type="button"
                    onClick={handleAddDecision}
                    className="bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 px-3 py-1.5 rounded-xl font-bold flex items-center space-x-1 cursor-pointer"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    <span>Add Decision</span>
                  </button>
                </div>

                <div className="space-y-1.5">
                  {keyDecisions.map((dec, i) => (
                    <div key={i} className="bg-emerald-50 p-2.5 rounded-xl border border-emerald-200 text-emerald-900 font-medium flex items-center space-x-2">
                      <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                      <span>{dec}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: ACTION ITEMS */}
          {activeTab === 'action-items' && (
            <div className="space-y-4">
              <h4 className="font-bold text-slate-900">Post-Meeting Action Items & Deliverables</h4>

              {/* Add Action Item Form */}
              <form onSubmit={handleCreateAction} className="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2">
                  <div className="sm:col-span-2">
                    <label className="block text-[10px] text-slate-500 font-bold mb-1">Task Deliverable Title *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Prepare curriculum outline"
                      value={newActionTitle}
                      onChange={(e) => setNewActionTitle(e.target.value)}
                      className="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-slate-900 font-medium"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] text-slate-500 font-bold mb-1">Assignee</label>
                    <select
                      value={newActionAssignee}
                      onChange={(e) => setNewActionAssignee(e.target.value)}
                      className="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-slate-900 font-medium"
                    >
                      {users.map((u) => (
                        <option key={u.id} value={u.id}>{u.name}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-[10px] text-slate-500 font-bold mb-1">Deadline</label>
                    <input
                      type="date"
                      value={newActionDeadline}
                      onChange={(e) => setNewActionDeadline(e.target.value)}
                      className="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-slate-900 font-medium"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-xl font-bold flex items-center space-x-1 cursor-pointer shadow-xs"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Assign Action Item</span>
                </button>
              </form>

              {/* Action items list */}
              <div className="space-y-2">
                {meetingActionItems.map((act) => {
                  const assignee = users.find((u) => u.id === act.assigneeId);
                  return (
                    <div key={act.id} className="bg-white p-3 rounded-2xl border border-slate-200 flex items-center justify-between shadow-xs">
                      <div>
                        <div className="font-bold text-slate-900">{act.title}</div>
                        <div className="text-[10px] text-slate-500 font-medium">
                          Assigned to: <strong className="text-slate-800">{assignee?.name}</strong> • Due: {act.deadline}
                        </div>
                      </div>
                      <span className={`px-2 py-0.5 rounded-lg text-[10px] font-bold ${
                        act.status === 'Completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                        'bg-amber-50 text-amber-800 border border-amber-200'
                      }`}>
                        {act.status}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

        </div>

      </div>
    </div>
  );
};

