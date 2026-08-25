import React, { useState, useEffect } from 'react';
import { User, Room, Meeting, Department, GlobalClashItem, ConflictCheckResult, SmartSuggestion } from '../types/index';
import { scanClashes, autoResolveClash, checkConflict } from '../services/api';
import { 
  ShieldAlert, AlertTriangle, CheckCircle2, Clock, MapPin, UserX, 
  Sparkles, RefreshCw, Filter, Calendar, Users, Eye, ArrowRight, 
  SlidersHorizontal, Check, Zap, Layers, BarChart3, AlertOctagon, HelpCircle
} from 'lucide-react';

interface ClashDetectorViewProps {
  rooms: Room[];
  meetings: Meeting[];
  users: User[];
  departments: Department[];
  currentUser: User;
  onRefreshData: () => void;
  onSelectMeeting: (meeting: Meeting) => void;
  onOpenNewMeeting: () => void;
}

export const ClashDetectorView: React.FC<ClashDetectorViewProps> = ({
  rooms,
  meetings,
  users,
  departments,
  currentUser,
  onRefreshData,
  onSelectMeeting,
  onOpenNewMeeting
}) => {
  const [activeTab, setActiveTab] = useState<'active_clashes' | 'simulator' | 'occupancy_grid' | 'participant_matrix'>('active_clashes');
  const [clashes, setClashes] = useState<GlobalClashItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [resolvingClashId, setResolvingClashId] = useState<string | null>(null);
  const [resolveSuccessMsg, setResolveSuccessMsg] = useState('');

  // Simulator State
  const todayStr = new Date().toISOString().split('T')[0];
  const [simDate, setSimDate] = useState(todayStr);
  const [simRoomId, setSimRoomId] = useState(rooms[0]?.id || 'room-1');
  const [simStartTime, setSimStartTime] = useState('10:00');
  const [simEndTime, setSimEndTime] = useState('11:30');
  const [simMode, setSimMode] = useState<'In-Person' | 'Online' | 'Hybrid'>('In-Person');
  const [simParticipantIds, setSimParticipantIds] = useState<string[]>([users[0]?.id, users[1]?.id].filter(Boolean));
  const [simResult, setSimResult] = useState<ConflictCheckResult | null>(null);
  const [isSimulating, setIsSimulating] = useState(false);

  // Participant Matrix State
  const [matrixDate, setMatrixDate] = useState(todayStr);
  const [matrixSelectedUsers, setMatrixSelectedUsers] = useState<string[]>(
    users.slice(0, 4).map(u => u.id)
  );

  useEffect(() => {
    loadClashes();
  }, [meetings]);

  const loadClashes = async () => {
    setIsLoading(true);
    try {
      const data = await scanClashes();
      setClashes(data);
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  // Run live simulation whenever simulator parameters change
  useEffect(() => {
    runSimulator();
  }, [simDate, simRoomId, simStartTime, simEndTime, simMode, simParticipantIds]);

  const runSimulator = async () => {
    setIsSimulating(true);
    try {
      const res = await checkConflict({
        roomId: simRoomId,
        date: simDate,
        startTime: simStartTime,
        endTime: simEndTime,
        participantUserIds: simParticipantIds,
        mode: simMode
      });
      setSimResult(res);
    } catch (e) {
      console.error(e);
    } finally {
      setIsSimulating(false);
    }
  };

  const handleApplyResolution = async (clash: GlobalClashItem, suggestion: SmartSuggestion) => {
    const targetMeeting = clash.meeting2 || clash.meeting1;
    if (!targetMeeting) return;

    setResolvingClashId(clash.id);
    try {
      await autoResolveClash({
        meetingId: targetMeeting.id,
        newRoomId: suggestion.roomId,
        newDate: suggestion.date,
        newStartTime: suggestion.startTime,
        newEndTime: suggestion.endTime
      });

      setResolveSuccessMsg(`Successfully auto-resolved "${targetMeeting.title}" to ${suggestion.roomName} (${suggestion.startTime} - ${suggestion.endTime})`);
      setTimeout(() => setResolveSuccessMsg(''), 5000);
      onRefreshData();
      loadClashes();
    } catch (err: any) {
      alert('Failed to apply resolution: ' + err.message);
    } finally {
      setResolvingClashId(null);
    }
  };

  // Metric computations
  const roomClashesCount = clashes.filter(c => c.clashType === 'room').length;
  const participantClashesCount = clashes.filter(c => c.clashType === 'participant').length;
  const statutoryClashesCount = clashes.filter(c => c.clashType === 'university_event').length;
  const capacityWarningsCount = clashes.filter(c => c.clashType === 'capacity').length;

  const hours = [
    '08:00', '09:00', '10:00', '11:00', '12:00', 
    '13:00', '14:00', '15:00', '16:00', '17:00'
  ];

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
        <div className="flex items-center space-x-3.5">
          <div className="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shadow-xs flex-shrink-0">
            <Zap className="w-6 h-6" />
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h2 className="text-xl font-bold tracking-tight text-slate-900">
                Automated Clash Detection & Resolution Engine
              </h2>
              <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                clashes.length > 0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300'
              }`}>
                {clashes.length} Active {clashes.length === 1 ? 'Clash' : 'Clashes'}
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              Real-time multi-dimensional conflict detection across venues, participant availability, statutory council blocks, and facility requirements.
            </p>
          </div>
        </div>

        <div className="flex items-center space-x-2.5">
          <button
            onClick={loadClashes}
            disabled={isLoading}
            className="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 flex items-center space-x-1.5 transition-colors cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin' : ''}`} />
            <span>Scan Database</span>
          </button>

          <button
            onClick={onOpenNewMeeting}
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs flex items-center space-x-1.5 transition-colors cursor-pointer"
          >
            <Calendar className="w-3.5 h-3.5" />
            <span>Schedule Meeting</span>
          </button>
        </div>
      </div>

      {/* Success Notification Alert */}
      {resolveSuccessMsg && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 rounded-2xl text-xs flex items-center justify-between shadow-xs animate-in fade-in">
          <div className="flex items-center space-x-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
            <span className="font-semibold">{resolveSuccessMsg}</span>
          </div>
          <button onClick={() => setResolveSuccessMsg('')} className="text-emerald-700 hover:text-emerald-900 text-xs font-bold cursor-pointer">
            Dismiss
          </button>
        </div>
      )}

      {/* Top Metric Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span className="font-semibold">Room Double-Bookings</span>
            <MapPin className="w-4 h-4 text-rose-500" />
          </div>
          <div className="text-2xl font-black text-rose-600 mt-2">{roomClashesCount}</div>
          <p className="text-[10px] text-slate-500 mt-0.5 font-medium">Overlapping venue locks</p>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span className="font-semibold">Participant Conflicts</span>
            <UserX className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-black text-amber-600 mt-2">{participantClashesCount}</div>
          <p className="text-[10px] text-slate-500 mt-0.5 font-medium">Double-scheduled members</p>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span className="font-semibold">Statutory Overlaps</span>
            <Calendar className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="text-2xl font-black text-indigo-600 mt-2">{statutoryClashesCount}</div>
          <p className="text-[10px] text-slate-500 mt-0.5 font-medium">Syndicate/Council locks</p>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span className="font-semibold">Capacity Constraints</span>
            <Users className="w-4 h-4 text-purple-500" />
          </div>
          <div className="text-2xl font-black text-purple-600 mt-2">{capacityWarningsCount}</div>
          <p className="text-[10px] text-slate-500 mt-0.5 font-medium">Oversubscribed venues</p>
        </div>
      </div>

      {/* Navigation Tab Bar */}
      <div className="flex items-center space-x-1.5 bg-slate-200/70 p-1.5 rounded-2xl border border-slate-200 w-fit">
        <button
          onClick={() => setActiveTab('active_clashes')}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center space-x-1.5 ${
            activeTab === 'active_clashes'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          }`}
        >
          <AlertOctagon className="w-3.5 h-3.5" />
          <span>Active Clashes & 1-Click Fix ({clashes.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('simulator')}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center space-x-1.5 ${
            activeTab === 'simulator'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          }`}
        >
          <SlidersHorizontal className="w-3.5 h-3.5" />
          <span>Live Clash Simulator</span>
        </button>

        <button
          onClick={() => setActiveTab('occupancy_grid')}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center space-x-1.5 ${
            activeTab === 'occupancy_grid'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          }`}
        >
          <Layers className="w-3.5 h-3.5" />
          <span>Campus Room Occupancy Timeline</span>
        </button>

        <button
          onClick={() => setActiveTab('participant_matrix')}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center space-x-1.5 ${
            activeTab === 'participant_matrix'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          }`}
        >
          <Users className="w-3.5 h-3.5" />
          <span>Participant Free-Window Matrix</span>
        </button>
      </div>

      {/* TAB 1: ACTIVE CLASHES & 1-CLICK RESOLUTION */}
      {activeTab === 'active_clashes' && (
        <div className="space-y-4">
          {clashes.length === 0 ? (
            <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center space-y-3 shadow-xs">
              <div className="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center mx-auto shadow-xs">
                <CheckCircle2 className="w-7 h-7" />
              </div>
              <h3 className="text-base font-bold text-slate-900">Zero Schedule Clashes in the Entire System</h3>
              <p className="text-xs text-slate-500 max-w-md mx-auto">
                All scheduled and pending university meetings have verified non-overlapping venue allocations, mutually available participants, and compliant capacities.
              </p>
              <button
                onClick={onOpenNewMeeting}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl cursor-pointer"
              >
                Schedule New Meeting
              </button>
            </div>
          ) : (
            <div className="space-y-3.5">
              {clashes.map((clash) => {
                const isCritical = clash.severity === 'critical';
                const room1 = rooms.find(r => r.id === clash.meeting1.roomId);
                const room2 = clash.meeting2 ? rooms.find(r => r.id === clash.meeting2.roomId) : undefined;

                return (
                  <div
                    key={clash.id}
                    className={`bg-white border rounded-3xl p-5 shadow-xs space-y-4 transition-all ${
                      isCritical ? 'border-rose-200 hover:border-rose-300' : 'border-amber-200 hover:border-amber-300'
                    }`}
                  >
                    {/* Clash Header */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                      <div className="flex items-center space-x-2.5">
                        <div className={`p-2 rounded-xl border ${
                          isCritical ? 'bg-rose-50 border-rose-200 text-rose-600' : 'bg-amber-50 border-amber-200 text-amber-600'
                        }`}>
                          {clash.clashType === 'room' && <MapPin className="w-4 h-4" />}
                          {clash.clashType === 'participant' && <UserX className="w-4 h-4" />}
                          {clash.clashType === 'university_event' && <Calendar className="w-4 h-4" />}
                          {clash.clashType === 'capacity' && <Users className="w-4 h-4" />}
                        </div>
                        <div>
                          <div className="flex items-center space-x-2">
                            <span className="font-bold text-sm text-slate-900">{clash.title}</span>
                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                              isCritical ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'
                            }`}>
                              {clash.clashType.toUpperCase()}
                            </span>
                          </div>
                          <p className="text-xs text-slate-500 mt-0.5">{clash.description}</p>
                        </div>
                      </div>

                      <div className="text-xs font-mono font-bold text-slate-600 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200 self-start sm:self-auto">
                        {clash.date} • {clash.timeWindow}
                      </div>
                    </div>

                    {/* Colliding Meetings Side-by-Side Comparison */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      {/* Meeting 1 */}
                      <div className="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {clash.meeting1.isUniversityWideEvent ? '★ Statutory Event' : 'Meeting A'}
                          </span>
                          <span className="text-[10px] bg-slate-200 text-slate-800 px-2 py-0.5 rounded-lg font-bold">
                            {clash.meeting1.status}
                          </span>
                        </div>
                        <div className="font-bold text-xs text-slate-900 line-clamp-1">{clash.meeting1.title}</div>
                        <div className="text-[11px] text-slate-600 space-y-1">
                          <div className="flex items-center space-x-1.5">
                            <Clock className="w-3 h-3 text-slate-400" />
                            <span>{clash.meeting1.startTime} - {clash.meeting1.endTime}</span>
                          </div>
                          <div className="flex items-center space-x-1.5">
                            <MapPin className="w-3 h-3 text-slate-400" />
                            <span>{room1?.name || 'No Room'} ({room1?.building})</span>
                          </div>
                          <div className="flex items-center space-x-1.5">
                            <Users className="w-3 h-3 text-slate-400" />
                            <span>{clash.meeting1.participants.length} Participants</span>
                          </div>
                        </div>
                        <button
                          onClick={() => onSelectMeeting(clash.meeting1)}
                          className="text-[11px] text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1 pt-1 cursor-pointer"
                        >
                          <Eye className="w-3 h-3" />
                          <span>View Meeting Details</span>
                        </button>
                      </div>

                      {/* Meeting 2 (if present) */}
                      {clash.meeting2 ? (
                        <div className="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 space-y-2">
                          <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                              Meeting B (Colliding Target)
                            </span>
                            <span className="text-[10px] bg-slate-200 text-slate-800 px-2 py-0.5 rounded-lg font-bold">
                              {clash.meeting2.status}
                            </span>
                          </div>
                          <div className="font-bold text-xs text-slate-900 line-clamp-1">{clash.meeting2.title}</div>
                          <div className="text-[11px] text-slate-600 space-y-1">
                            <div className="flex items-center space-x-1.5">
                              <Clock className="w-3 h-3 text-slate-400" />
                              <span>{clash.meeting2.startTime} - {clash.meeting2.endTime}</span>
                            </div>
                            <div className="flex items-center space-x-1.5">
                              <MapPin className="w-3 h-3 text-slate-400" />
                              <span>{room2?.name || 'No Room'} ({room2?.building})</span>
                            </div>
                            <div className="flex items-center space-x-1.5">
                              <Users className="w-3 h-3 text-slate-400" />
                              <span>{clash.meeting2.participants.length} Participants</span>
                            </div>
                          </div>
                          <button
                            onClick={() => onSelectMeeting(clash.meeting2!)}
                            className="text-[11px] text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1 pt-1 cursor-pointer"
                          >
                            <Eye className="w-3 h-3" />
                            <span>View Meeting Details</span>
                          </button>
                        </div>
                      ) : (
                        <div className="bg-purple-50/50 border border-purple-200 rounded-2xl p-3.5 space-y-2 flex flex-col justify-center">
                          <span className="text-[10px] font-bold uppercase tracking-wider text-purple-700">
                            Capacity Constraint Analysis
                          </span>
                          <p className="text-xs text-purple-900 font-medium">
                            The meeting roster of {clash.meeting1.participants.length} exceeds room seat limit of {room1?.capacity} by {clash.meeting1.participants.length - (room1?.capacity || 0)} attendees.
                          </p>
                        </div>
                      )}
                    </div>

                    {/* 1-Click Smart Resolution Suggestions */}
                    {clash.suggestions && clash.suggestions.length > 0 && (
                      <div className="pt-2 border-t border-slate-100 space-y-2">
                        <div className="flex items-center space-x-1.5 text-xs font-bold text-indigo-700">
                          <Sparkles className="w-4 h-4 text-indigo-600" />
                          <span>1-Click Automatic Resolution Options:</span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          {clash.suggestions.map((sug, sIdx) => (
                            <div
                              key={sIdx}
                              className="bg-indigo-50/40 border border-indigo-200 rounded-2xl p-3 flex flex-col justify-between space-y-2"
                            >
                              <div>
                                <div className="font-bold text-xs text-slate-900 flex items-center justify-between">
                                  <span>{sug.roomName}</span>
                                  <span className="text-[10px] bg-white border border-indigo-200 text-indigo-700 font-bold px-2 py-0.5 rounded-lg">
                                    {sug.startTime} - {sug.endTime}
                                  </span>
                                </div>
                                <p className="text-[11px] text-slate-600 mt-1 leading-snug">{sug.reason}</p>
                              </div>

                              <button
                                type="button"
                                disabled={resolvingClashId === clash.id}
                                onClick={() => handleApplyResolution(clash, sug)}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold py-1.5 rounded-xl shadow-xs transition-colors cursor-pointer flex items-center justify-center space-x-1"
                              >
                                <Zap className="w-3.5 h-3.5" />
                                <span>{resolvingClashId === clash.id ? 'Applying Fix...' : 'Apply This Resolution'}</span>
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* TAB 2: INTERACTIVE CLASH SIMULATOR */}
      {activeTab === 'simulator' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Simulator Form Controls */}
          <div className="lg:col-span-5 bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 text-xs">
            <div className="flex items-center space-x-2 font-bold text-sm text-slate-900 border-b border-slate-100 pb-3">
              <SlidersHorizontal className="w-4 h-4 text-indigo-600" />
              <span>Meeting Parameter Sandbox</span>
            </div>

            <div>
              <label className="block text-slate-700 font-bold mb-1">Target Date</label>
              <input
                type="date"
                value={simDate}
                onChange={(e) => setSimDate(e.target.value)}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-indigo-600"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-slate-700 font-bold mb-1">Start Time</label>
                <input
                  type="time"
                  value={simStartTime}
                  onChange={(e) => setSimStartTime(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-indigo-600"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-bold mb-1">End Time</label>
                <input
                  type="time"
                  value={simEndTime}
                  onChange={(e) => setSimEndTime(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-indigo-600"
                />
              </div>
            </div>

            <div>
              <label className="block text-slate-700 font-bold mb-1">Proposed Room / Hall</label>
              <select
                value={simRoomId}
                onChange={(e) => setSimRoomId(e.target.value)}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-indigo-600"
              >
                {rooms.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name} (Cap: {r.capacity}) - {r.building}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-slate-700 font-bold mb-1">Meeting Mode</label>
              <select
                value={simMode}
                onChange={(e) => setSimMode(e.target.value as any)}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-indigo-600"
              >
                <option value="In-Person">In-Person</option>
                <option value="Hybrid">Hybrid (Requires VC Terminal)</option>
                <option value="Online">Online Only</option>
              </select>
            </div>

            {/* Participant selector */}
            <div className="space-y-1.5 pt-1">
              <label className="block text-slate-700 font-bold">Select Attendees to Check ({simParticipantIds.length})</label>
              <div className="grid grid-cols-2 gap-1.5 max-h-36 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200">
                {users.map((u) => {
                  const isChecked = simParticipantIds.includes(u.id);
                  return (
                    <div
                      key={u.id}
                      onClick={() => {
                        if (isChecked) {
                          setSimParticipantIds(simParticipantIds.filter(id => id !== u.id));
                        } else {
                          setSimParticipantIds([...simParticipantIds, u.id]);
                        }
                      }}
                      className={`p-1.5 rounded-lg border text-[10px] cursor-pointer flex items-center space-x-1.5 truncate ${
                        isChecked ? 'bg-indigo-50 border-indigo-300 text-indigo-900 font-bold' : 'bg-white border-slate-200 text-slate-600'
                      }`}
                    >
                      <div className={`w-3 h-3 rounded-sm flex items-center justify-center ${isChecked ? 'bg-indigo-600 text-white' : 'border border-slate-300'}`}>
                        {isChecked && <Check className="w-2.5 h-2.5" />}
                      </div>
                      <span className="truncate">{u.name}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Simulator Live Feedback */}
          <div className="lg:col-span-7 space-y-4">
            <div className="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center space-x-2">
                  <div className="font-bold text-sm text-slate-900">Real-Time Simulation Feedback</div>
                  {isSimulating && <RefreshCw className="w-3.5 h-3.5 text-indigo-600 animate-spin" />}
                </div>
                <span className="text-xs text-slate-500 font-mono">
                  {simDate} @ {simStartTime} - {simEndTime}
                </span>
              </div>

              {simResult ? (
                <div className="space-y-4">
                  {/* Status Banner */}
                  {!simResult.hasConflict && simResult.conflicts.length === 0 ? (
                    <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-emerald-900 text-xs flex items-center space-x-3">
                      <CheckCircle2 className="w-6 h-6 text-emerald-600 flex-shrink-0" />
                      <div>
                        <div className="font-bold text-emerald-900 text-sm">100% Free & Ready to Book!</div>
                        <div className="text-emerald-800 mt-0.5">
                          Zero conflicts found. The selected venue, time interval, and all {simParticipantIds.length} participants are completely available.
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-900 text-xs flex items-center space-x-3">
                        <AlertOctagon className="w-6 h-6 text-rose-600 flex-shrink-0" />
                        <div>
                          <div className="font-bold text-rose-900 text-sm">
                            {simResult.conflicts.length} Clash Constraint{simResult.conflicts.length > 1 ? 's' : ''} Detected
                          </div>
                          <div className="text-rose-800 mt-0.5">
                            This combination cannot be booked without schedule adjustments.
                          </div>
                        </div>
                      </div>

                      {/* Conflict details */}
                      <div className="space-y-1.5 pt-1">
                        {simResult.conflicts.map((c, i) => (
                          <div key={i} className="bg-slate-50 border border-rose-200 rounded-xl p-3 text-xs space-y-0.5">
                            <div className="font-bold text-rose-900 flex items-center justify-between">
                              <span>{c.title}</span>
                              {c.existingTime && <span className="font-mono text-[10px] bg-rose-100 px-2 py-0.5 rounded">{c.existingTime}</span>}
                            </div>
                            <p className="text-slate-600 text-[11px]">{c.description}</p>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Alternative suggestions */}
                  {simResult.suggestions.length > 0 && (
                    <div className="space-y-2 pt-2 border-t border-slate-100">
                      <div className="text-xs font-bold text-indigo-700 flex items-center space-x-1">
                        <Sparkles className="w-4 h-4 text-indigo-600" />
                        <span>Recommended Conflict-Free Alternatives:</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {simResult.suggestions.map((sug, i) => (
                          <div key={i} className="bg-indigo-50/50 border border-indigo-200 rounded-xl p-3 text-xs space-y-1.5">
                            <div className="font-bold text-slate-900 flex items-center justify-between">
                              <span>{sug.roomName}</span>
                              <span className="text-[10px] bg-white text-indigo-700 font-bold px-2 py-0.5 rounded-lg border border-indigo-200">
                                {sug.startTime} - {sug.endTime}
                              </span>
                            </div>
                            <p className="text-[11px] text-slate-600 leading-snug">{sug.reason}</p>
                            <button
                              type="button"
                              onClick={() => {
                                if (sug.roomId) setSimRoomId(sug.roomId);
                                if (sug.startTime) setSimStartTime(sug.startTime);
                                if (sug.endTime) setSimEndTime(sug.endTime);
                              }}
                              className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold py-1 rounded-lg cursor-pointer transition-colors"
                            >
                              Simulate This Slot
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ) : null}
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: CAMPUS ROOM OCCUPANCY TIMELINE */}
      {activeTab === 'occupancy_grid' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 text-xs">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div className="flex items-center space-x-2">
              <Layers className="w-4 h-4 text-indigo-600" />
              <span className="font-bold text-sm text-slate-900">Campus Venue Timetable Heatmap</span>
            </div>

            <div className="flex items-center space-x-2">
              <label className="text-slate-600 font-medium">Select Date:</label>
              <input
                type="date"
                value={simDate}
                onChange={(e) => setSimDate(e.target.value)}
                className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-medium focus:outline-none focus:border-indigo-600"
              />
            </div>
          </div>

          <div className="overflow-x-auto">
            <div className="min-w-[700px] space-y-2">
              {/* Hours Header */}
              <div className="grid grid-cols-11 text-center font-mono font-bold text-[10px] text-slate-500 pb-2 border-b border-slate-100">
                <div className="text-left font-sans">Venue Name</div>
                {hours.map((h, i) => (
                  <div key={i}>{h}</div>
                ))}
              </div>

              {/* Room Rows */}
              {rooms.map((room) => {
                const roomMeetings = meetings.filter(
                  m => m.date === simDate && m.roomId === room.id && m.status !== 'Rejected' && m.status !== 'Cancelled'
                );

                return (
                  <div key={room.id} className="grid grid-cols-11 items-center p-2 rounded-xl bg-slate-50 border border-slate-200 hover:border-indigo-300 transition-colors">
                    <div className="pr-2">
                      <div className="font-bold text-slate-900 truncate">{room.name}</div>
                      <div className="text-[10px] text-slate-500 font-medium">Cap: {room.capacity}</div>
                    </div>

                    <div className="col-span-10 relative h-8 bg-white border border-slate-200 rounded-lg overflow-hidden flex items-center">
                      {roomMeetings.length === 0 ? (
                        <div className="w-full text-center text-[10px] text-emerald-600 font-semibold">
                          100% Available All Day
                        </div>
                      ) : (
                        roomMeetings.map((m) => {
                          const [sh, sm] = m.startTime.split(':').map(Number);
                          const [eh, em] = m.endTime.split(':').map(Number);
                          const startMins = (sh - 8) * 60 + sm;
                          const durationMins = (eh * 60 + em) - (sh * 60 + sm);
                          const totalDayMins = 10 * 60; // 08:00 to 18:00

                          const leftPct = Math.max(0, Math.min(100, (startMins / totalDayMins) * 100));
                          const widthPct = Math.max(5, Math.min(100 - leftPct, (durationMins / totalDayMins) * 100));

                          return (
                            <div
                              key={m.id}
                              onClick={() => onSelectMeeting(m)}
                              style={{ left: `${leftPct}%`, width: `${widthPct}%` }}
                              title={`${m.title} (${m.startTime} - ${m.endTime})`}
                              className="absolute h-6 top-1 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold px-1.5 truncate flex items-center shadow-xs cursor-pointer"
                            >
                              <span className="truncate">{m.startTime}-{m.endTime}: {m.title}</span>
                            </div>
                          );
                        })
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: PARTICIPANT FREE-WINDOW MATRIX */}
      {activeTab === 'participant_matrix' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 text-xs">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
              <div className="flex items-center space-x-2">
                <Users className="w-4 h-4 text-indigo-600" />
                <span className="font-bold text-sm text-slate-900">Key Member Schedule Overlap Finder</span>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Check simultaneous free slots across key academic authorities and statutory members.
              </p>
            </div>

            <div className="flex items-center space-x-2">
              <label className="text-slate-600 font-medium">Date:</label>
              <input
                type="date"
                value={matrixDate}
                onChange={(e) => setMatrixDate(e.target.value)}
                className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-medium focus:outline-none focus:border-indigo-600"
              />
            </div>
          </div>

          {/* User selector chips */}
          <div className="space-y-1.5">
            <label className="font-bold text-slate-700">Select University Officials to Cross-Examine:</label>
            <div className="flex flex-wrap gap-2">
              {users.map((u) => {
                const isSelected = matrixSelectedUsers.includes(u.id);
                return (
                  <button
                    key={u.id}
                    onClick={() => {
                      if (isSelected) {
                        if (matrixSelectedUsers.length > 1) {
                          setMatrixSelectedUsers(matrixSelectedUsers.filter(id => id !== u.id));
                        }
                      } else {
                        setMatrixSelectedUsers([...matrixSelectedUsers, u.id]);
                      }
                    }}
                    className={`px-3 py-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer flex items-center space-x-1.5 ${
                      isSelected
                        ? 'bg-indigo-50 border-indigo-300 text-indigo-900 shadow-xs'
                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                    }`}
                  >
                    <span>{u.name}</span>
                    <span className="text-[10px] text-slate-400">({u.role})</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Member Visual Timelines */}
          <div className="space-y-2.5 pt-2">
            {matrixSelectedUsers.map((uId) => {
              const u = users.find(user => user.id === uId);
              if (!u) return null;

              const userMeetings = meetings.filter(
                m => m.date === matrixDate &&
                     m.participants.some(p => p.userId === uId) &&
                     m.status !== 'Rejected' &&
                     m.status !== 'Cancelled'
              );

              return (
                <div key={uId} className="bg-slate-50 border border-slate-200 rounded-2xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-center space-x-2.5 w-48 flex-shrink-0">
                    <img src={u.avatar} alt={u.name} className="w-8 h-8 rounded-full border border-slate-200" />
                    <div className="truncate">
                      <div className="font-bold text-slate-900 truncate">{u.name}</div>
                      <div className="text-[10px] text-slate-500 truncate">{u.designation}</div>
                    </div>
                  </div>

                  {/* Schedule Bar */}
                  <div className="flex-1 bg-white border border-slate-200 rounded-xl p-2 flex flex-wrap gap-2">
                    {userMeetings.length === 0 ? (
                      <span className="text-emerald-700 font-semibold text-[11px] flex items-center space-x-1">
                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        <span>Completely Free All Day</span>
                      </span>
                    ) : (
                      userMeetings.map((m) => (
                        <div
                          key={m.id}
                          onClick={() => onSelectMeeting(m)}
                          className="bg-amber-50 border border-amber-200 text-amber-900 px-2 py-1 rounded-lg text-[10px] font-bold flex items-center space-x-1 cursor-pointer hover:bg-amber-100"
                        >
                          <Clock className="w-3 h-3 text-amber-600" />
                          <span>{m.startTime}-{m.endTime}: {m.title}</span>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

    </div>
  );
};
