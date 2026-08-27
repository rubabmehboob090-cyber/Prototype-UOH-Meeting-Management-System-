import React, { useState, useEffect } from 'react';
import { User, Department, Room, ConflictCheckResult, SmartSuggestion } from '../types/index';
import { checkConflict, createMeeting } from '../services/api';
import { ConflictWarning } from './ConflictWarning';
import { X, Plus, Trash2, Calendar, Clock, Users, FileText, CheckCircle, ShieldAlert, Lock } from 'lucide-react';
import { getRolePermissions } from '../utils/rbac';

interface MeetingRequestModalProps {
  isOpen: boolean;
  onClose: () => void;
  users: User[];
  departments: Department[];
  rooms: Room[];
  currentUser: User;
  onMeetingCreated: () => void;
  initialRoomId?: string;
  initialDate?: string;
}

export const MeetingRequestModal: React.FC<MeetingRequestModalProps> = ({
  isOpen,
  onClose,
  users,
  departments,
  rooms,
  currentUser,
  onMeetingCreated,
  initialRoomId,
  initialDate
}) => {
  const permissions = getRolePermissions(currentUser.role);
  const todayStr = new Date().toISOString().split('T')[0];

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [meetingType, setMeetingType] = useState<any>('Departmental');
  const [departmentId, setDepartmentId] = useState(currentUser.departmentId || departments[0]?.id || '');
  const [roomId, setRoomId] = useState(initialRoomId || rooms[0]?.id || '');
  const [date, setDate] = useState(initialDate || todayStr);
  const [startTime, setStartTime] = useState('10:00');
  const [endTime, setEndTime] = useState('11:30');
  const [mode, setMode] = useState<'In-Person' | 'Online' | 'Hybrid'>('In-Person');
  const [onlineLink, setOnlineLink] = useState('');
  const [chairId, setChairId] = useState(currentUser.id);
  const [selectedParticipantIds, setSelectedParticipantIds] = useState<string[]>([currentUser.id]);
  const [agendaInput, setAgendaInput] = useState('');
  const [agendaItems, setAgendaItems] = useState<string[]>([
    'Opening remarks by the Chair',
    'Review and approval of previous minutes'
  ]);
  const [isUniversityWideEvent, setIsUniversityWideEvent] = useState(false);

  // Conflict Engine State
  const [conflictResult, setConflictResult] = useState<ConflictCheckResult>({
    hasConflict: false,
    conflicts: [],
    suggestions: []
  });
  const [isCheckingConflict, setIsCheckingConflict] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  // Live Conflict Engine Trigger
  useEffect(() => {
    if (!isOpen || !permissions.canCreateMeeting) return;

    const timer = setTimeout(() => {
      runConflictCheck();
    }, 300);

    return () => clearTimeout(timer);
  }, [roomId, date, startTime, endTime, selectedParticipantIds, isOpen]);

  const runConflictCheck = async () => {
    setIsCheckingConflict(true);
    try {
      const res = await checkConflict({
        roomId,
        date,
        startTime,
        endTime,
        participantUserIds: selectedParticipantIds
      });
      setConflictResult(res);
    } catch (e) {
      console.error(e);
    } finally {
      setIsCheckingConflict(false);
    }
  };

  const handleApplySuggestion = (suggestion: SmartSuggestion) => {
    if (suggestion.roomId) setRoomId(suggestion.roomId);
    if (suggestion.date) setDate(suggestion.date);
    if (suggestion.startTime) setStartTime(suggestion.startTime);
    if (suggestion.endTime) setEndTime(suggestion.endTime);
  };

  const handleAddAgenda = () => {
    if (agendaInput.trim()) {
      setAgendaItems([...agendaItems, agendaInput.trim()]);
      setAgendaInput('');
    }
  };

  const handleRemoveAgenda = (idx: number) => {
    setAgendaItems(agendaItems.filter((_, i) => i !== idx));
  };

  const toggleParticipant = (uId: string) => {
    if (selectedParticipantIds.includes(uId)) {
      if (uId === chairId) return; // Chair must stay selected
      setSelectedParticipantIds(selectedParticipantIds.filter(id => id !== uId));
    } else {
      setSelectedParticipantIds([...selectedParticipantIds, uId]);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!permissions.canCreateMeeting) {
      setErrorMsg('Faculty members cannot directly create meetings. Please request through your HOD.');
      return;
    }
    if (!title.trim()) {
      setErrorMsg('Please enter a meeting title.');
      return;
    }
    setErrorMsg('');
    setIsSubmitting(true);

    try {
      await createMeeting({
        title,
        description,
        meetingType,
        departmentId,
        requesterId: currentUser.id,
        chairId,
        roomId,
        date,
        startTime,
        endTime,
        mode,
        onlineLink,
        participantUserIds: selectedParticipantIds,
        agendaItems,
        isUniversityWideEvent
      });

      setIsSubmitting(false);
      onMeetingCreated();
      onClose();
    } catch (err: any) {
      setErrorMsg(err.message || 'Failed to submit meeting request');
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
      <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl max-h-[92vh] overflow-y-auto shadow-2xl text-slate-900 flex flex-col my-auto">
        
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
          <div className="flex items-center space-x-2.5">
            <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
              <Calendar className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Schedule University Meeting</h3>
              <p className="text-xs text-slate-500">
                UoH Scheduling Engine • Conflict Engine & Authority Routing
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-700 p-1.5 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Access Restriction Notice for Faculty */}
        {!permissions.canCreateMeeting ? (
          <div className="p-8 text-center space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mx-auto">
              <Lock className="w-6 h-6" />
            </div>
            <div>
              <h4 className="text-base font-bold text-slate-900">Role Restriction: Faculty Access Only</h4>
              <p className="text-xs text-slate-600 max-w-md mx-auto mt-1 leading-relaxed">
                As a Faculty Member, you do not have direct authorization to initiate new university meeting requests. Please contact your Head of Department (HOD) or Departmental Administrative Assistant to submit a schedule proposal on your behalf.
              </p>
            </div>
            <button
              onClick={onClose}
              className="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl cursor-pointer"
            >
              Understand & Close
            </button>
          </div>
        ) : (
          /* Modal Form Body */
          <form onSubmit={handleSubmit} className="p-6 space-y-5 text-xs">
            {currentUser.role === 'Faculty/Staff' && (
              <div className="bg-indigo-50 border border-indigo-200 text-indigo-900 p-3 rounded-2xl flex items-center space-x-2.5 text-xs">
                <CheckCircle className="w-4 h-4 text-indigo-600 flex-shrink-0" />
                <span>
                  <strong>Faculty Request Workflow:</strong> Your meeting request will be submitted as "Pending Approval" to your Head of Department / Office Admin. Faculty cannot directly confirm meetings without approval.
                </span>
              </div>
            )}

            {errorMsg && (
              <div className="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-2xl flex items-center space-x-2">
                <ShieldAlert className="w-4 h-4 text-rose-600" />
                <span>{errorMsg}</span>
              </div>
            )}

            {/* Section 1: Meeting Title & Type */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-2 space-y-1">
                <label className="block text-slate-700 font-bold">Meeting Title *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 19th Board of Faculty Meeting"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div className="space-y-1">
                <label className="block text-slate-700 font-bold">Meeting Category / Tier</label>
                <select
                  value={meetingType}
                  onChange={(e) => setMeetingType(e.target.value as any)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                >
                  <option value="Departmental">Departmental Meeting</option>
                  <option value="Academic Council">Academic Council (Statutory)</option>
                  <option value="Syndicate">Syndicate Committee</option>
                  <option value="Senate">Senate Session</option>
                  <option value="ORIC / Research">ORIC / Research Grant</option>
                  <option value="Committee">Special Committee</option>
                  <option value="Routine Admin">Routine Administrative</option>
                  <option value="Emergency">Emergency Meeting</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label className="block text-slate-700 font-bold">Hosting Department / Office</label>
                <select
                  value={departmentId}
                  onChange={(e) => setDepartmentId(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                >
                  {departments.map((d) => (
                    <option key={d.id} value={d.id}>{d.name} ({d.code})</option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="block text-slate-700 font-bold">Meeting Chair / Presiding Officer</label>
                <select
                  value={chairId}
                  onChange={(e) => {
                    setChairId(e.target.value);
                    if (!selectedParticipantIds.includes(e.target.value)) {
                      setSelectedParticipantIds([...selectedParticipantIds, e.target.value]);
                    }
                  }}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                >
                  {users.map((u) => (
                    <option key={u.id} value={u.id}>{u.name} ({u.designation})</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Section 2: Date, Time & Venue */}
            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
              <div className="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center space-x-1.5">
                <Clock className="w-3.5 h-3.5" />
                <span>Schedule, Venue & Mode</span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                  <label className="block text-slate-700 font-medium mb-1">Date</label>
                  <input
                    type="date"
                    required
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  />
                </div>

                <div>
                  <label className="block text-slate-700 font-medium mb-1">Start Time</label>
                  <input
                    type="time"
                    required
                    value={startTime}
                    onChange={(e) => setStartTime(e.target.value)}
                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  />
                </div>

                <div>
                  <label className="block text-slate-700 font-medium mb-1">End Time</label>
                  <input
                    type="time"
                    required
                    value={endTime}
                    onChange={(e) => setEndTime(e.target.value)}
                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  />
                </div>

                <div>
                  <label className="block text-slate-700 font-medium mb-1">Meeting Mode</label>
                  <select
                    value={mode}
                    onChange={(e) => setMode(e.target.value as any)}
                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  >
                    <option value="In-Person">In-Person</option>
                    <option value="Hybrid">Hybrid (In-Person + Online)</option>
                    <option value="Online">Online Only</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <div>
                  <label className="block text-slate-700 font-medium mb-1">Select Room / Venue</label>
                  <select
                    value={roomId}
                    onChange={(e) => setRoomId(e.target.value)}
                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                  >
                    {rooms.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name} (Cap: {r.capacity}) - {r.building}
                      </option>
                    ))}
                  </select>
                </div>

                {mode !== 'In-Person' && (
                  <div>
                    <label className="block text-slate-700 font-medium mb-1">Video Call Link (Google Meet / Zoom)</label>
                    <input
                      type="url"
                      placeholder="https://meet.uoh.edu.pk/..."
                      value={onlineLink}
                      onChange={(e) => setOnlineLink(e.target.value)}
                      className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                    />
                  </div>
                )}
              </div>

              {/* University Wide Event Checkbox */}
              <div className="pt-2 flex items-center space-x-2">
                <input
                  type="checkbox"
                  id="univEvent"
                  checked={isUniversityWideEvent}
                  onChange={(e) => setIsUniversityWideEvent(e.target.checked)}
                  className="rounded bg-white border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                />
                <label htmlFor="univEvent" className="text-xs text-amber-800 font-bold cursor-pointer">
                  Mark as Statutory / University-Wide Event (Will reserve global schedule block)
                </label>
              </div>
            </div>

            {/* SECTION 3: REAL-TIME CONFLICT ENGINE WARNING & SMART SOLVER */}
            <ConflictWarning
              conflictResult={conflictResult}
              onApplySuggestion={handleApplySuggestion}
            />

            {/* Section 4: Participants Selection */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="block text-slate-700 font-bold flex items-center space-x-1.5">
                  <Users className="w-4 h-4 text-indigo-600" />
                  <span>Select Required Participants ({selectedParticipantIds.length})</span>
                </label>
                <span className="text-[10px] text-slate-500">Click user to toggle attendance</span>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 max-h-40 overflow-y-auto p-2 bg-slate-50 rounded-2xl border border-slate-200">
                {users.map((u) => {
                  const isSelected = selectedParticipantIds.includes(u.id);
                  const isChair = u.id === chairId;
                  return (
                    <div
                      key={u.id}
                      onClick={() => toggleParticipant(u.id)}
                      className={`p-2 rounded-xl border text-[11px] cursor-pointer transition-all flex items-center space-x-2 ${
                        isSelected
                          ? 'bg-indigo-50 border-indigo-300 text-indigo-900 font-bold'
                          : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100'
                      }`}
                    >
                      <img
                        src={u.avatar}
                        alt={u.name}
                        className="w-6 h-6 rounded-full object-cover flex-shrink-0 border border-slate-200"
                      />
                      <div className="truncate">
                        <div className="font-bold truncate">{u.name}</div>
                        <div className="text-[9px] text-slate-500 truncate">
                          {isChair ? '★ Chair' : u.role}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Section 5: Agendas */}
            <div className="space-y-2">
              <label className="block text-slate-700 font-bold flex items-center space-x-1.5">
                <FileText className="w-4 h-4 text-indigo-600" />
                <span>Meeting Agendas & Discussion Topics</span>
              </label>

              <div className="flex space-x-2">
                <input
                  type="text"
                  placeholder="Add agenda topic..."
                  value={agendaInput}
                  onChange={(e) => setAgendaInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      handleAddAgenda();
                    }
                  }}
                  className="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
                <button
                  type="button"
                  onClick={handleAddAgenda}
                  className="bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 px-3 py-1.5 rounded-xl flex items-center space-x-1 cursor-pointer font-bold"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Add</span>
                </button>
              </div>

              <div className="space-y-1.5">
                {agendaItems.map((item, idx) => (
                  <div key={idx} className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 flex items-center justify-between">
                    <span className="text-slate-800 text-[11px] font-mono">
                      {idx + 1}. {item}
                    </span>
                    <button
                      type="button"
                      onClick={() => handleRemoveAgenda(idx)}
                      className="text-slate-400 hover:text-rose-600 p-1 cursor-pointer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {/* Modal Footer */}
            <div className="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center space-x-1.5 cursor-pointer disabled:opacity-50"
              >
                <CheckCircle className="w-4 h-4" />
                <span>{isSubmitting ? 'Submitting...' : 'Submit Meeting Request'}</span>
              </button>
            </div>
          </form>
        )}

      </div>
    </div>
  );
};
