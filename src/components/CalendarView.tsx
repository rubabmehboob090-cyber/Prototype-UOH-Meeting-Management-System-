import React, { useState } from 'react';
import { Meeting, Room, Department, User } from '../types/index.ts';
import { Calendar, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { getRolePermissions } from '../utils/rbac.ts';

interface CalendarViewProps {
  meetings: Meeting[];
  rooms: Room[];
  departments: Department[];
  currentUser: User;
  onSelectMeeting: (meeting: Meeting) => void;
  onOpenNewMeeting: () => void;
}

export const CalendarView: React.FC<CalendarViewProps> = ({
  meetings,
  rooms,
  departments,
  currentUser,
  onSelectMeeting,
  onOpenNewMeeting
}) => {
  const permissions = getRolePermissions(currentUser.role);

  const [calendarViewMode, setCalendarViewMode] = useState<'month' | 'list'>('month');
  const [selectedRoomFilter, setSelectedRoomFilter] = useState<string>('all');
  const [selectedDeptFilter, setSelectedDeptFilter] = useState<string>('all');
  const [personalOnly, setPersonalOnly] = useState(false);

  // Current Date Controls
  const [currentDate, setCurrentDate] = useState(new Date());

  const handlePrevDate = () => {
    const d = new Date(currentDate);
    if (calendarViewMode === 'month') d.setMonth(d.getMonth() - 1);
    setCurrentDate(d);
  };

  const handleNextDate = () => {
    const d = new Date(currentDate);
    if (calendarViewMode === 'month') d.setMonth(d.getMonth() + 1);
    setCurrentDate(d);
  };

  // Filter meetings
  const filteredMeetings = meetings.filter((m) => {
    if (selectedRoomFilter !== 'all' && m.roomId !== selectedRoomFilter) return false;
    if (selectedDeptFilter !== 'all' && m.departmentId !== selectedDeptFilter) return false;
    if (personalOnly) {
      const isUserParticipant = m.participants.some((p) => p.userId === currentUser.id);
      if (!isUserParticipant && m.requesterId !== currentUser.id) return false;
    }
    return true;
  });

  // Month Calendar Matrix calculation
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const monthName = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });

  const firstDayOfMonth = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Calendar cells
  const daysArray = [];
  for (let i = 0; i < firstDayOfMonth; i++) {
    daysArray.push(null);
  }
  for (let d = 1; d <= daysInMonth; d++) {
    daysArray.push(d);
  }

  const formatDayStr = (dayNum: number) => {
    const mStr = (month + 1).toString().padStart(2, '0');
    const dStr = dayNum.toString().padStart(2, '0');
    return `${year}-${mStr}-${dStr}`;
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header & Controls */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
          <div className="flex items-center space-x-2">
            <Calendar className="w-6 h-6 text-indigo-600" />
            <h2 className="text-xl font-bold tracking-tight text-slate-900">Master Schedule & Room Calendar</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Authorized views for statutory councils, departmental sessions, and campus room schedules.
          </p>
        </div>

        {permissions.canCreateMeeting && (
          <button
            onClick={onOpenNewMeeting}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm flex items-center space-x-1.5 transition-colors cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Book Slot</span>
          </button>
        )}
      </div>

      {/* Filter Bar */}
      <div className="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
        
        {/* Date Navigator */}
        <div className="flex items-center space-x-3 w-full md:w-auto justify-between md:justify-start">
          <div className="flex items-center space-x-1">
            <button
              onClick={handlePrevDate}
              className="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg cursor-pointer"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={handleNextDate}
              className="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg cursor-pointer"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
          <span className="text-sm font-bold text-slate-900 tracking-wide">{monthName}</span>
          <button
            onClick={() => setCurrentDate(new Date())}
            className="text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-lg font-bold cursor-pointer border border-slate-200"
          >
            Today
          </button>
        </div>

        {/* Dropdown Filters */}
        <div className="flex flex-wrap items-center gap-2 text-xs w-full md:w-auto">
          
          <select
            value={selectedRoomFilter}
            onChange={(e) => setSelectedRoomFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500 font-medium"
          >
            <option value="all">All Venue Rooms</option>
            {rooms.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </select>

          <select
            value={selectedDeptFilter}
            onChange={(e) => setSelectedDeptFilter(e.target.value)}
            className="bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500 font-medium"
          >
            <option value="all">All Departments</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>{d.code} - {d.name}</option>
            ))}
          </select>

          <button
            onClick={() => setPersonalOnly(!personalOnly)}
            className={`px-3 py-1.5 rounded-xl text-xs font-bold border transition-colors cursor-pointer ${
              personalOnly 
                ? 'bg-indigo-600 border-indigo-600 text-white' 
                : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'
            }`}
          >
            My Meetings Only
          </button>

          {/* View Mode Toggle Buttons */}
          <div className="flex items-center bg-slate-100 border border-slate-200 rounded-xl p-0.5 ml-auto">
            {(['month', 'list'] as const).map((mode) => (
              <button
                key={mode}
                onClick={() => setCalendarViewMode(mode)}
                className={`px-3 py-1 rounded-lg text-[11px] font-bold capitalize transition-colors cursor-pointer ${
                  calendarViewMode === mode 
                    ? 'bg-indigo-600 text-white shadow-sm' 
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                {mode}
              </button>
            ))}
          </div>

        </div>

      </div>

      {/* Month View Matrix */}
      {calendarViewMode === 'month' && (
        <div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
          {/* Days Header */}
          <div className="grid grid-cols-7 bg-slate-50 text-slate-600 font-bold text-center text-xs py-2.5 border-b border-slate-200">
            <div>Sun</div>
            <div>Mon</div>
            <div>Tue</div>
            <div>Wed</div>
            <div>Thu</div>
            <div>Fri</div>
            <div>Sat</div>
          </div>

          {/* Days Grid */}
          <div className="grid grid-cols-7 divide-x divide-y divide-slate-100 bg-white">
            {daysArray.map((dayNum, idx) => {
              if (dayNum === null) {
                return <div key={idx} className="min-h-[100px] bg-slate-50/50 p-2" />;
              }

              const dateStr = formatDayStr(dayNum);
              const dayMeetings = filteredMeetings.filter((m) => m.date === dateStr);
              const isToday = dateStr === new Date().toISOString().split('T')[0];

              return (
                <div 
                  key={idx} 
                  className={`min-h-[110px] p-2 transition-colors ${
                    isToday ? 'bg-indigo-50/40' : 'hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-center justify-between mb-1">
                    <span className={`text-xs font-bold w-6 h-6 flex items-center justify-center rounded-full ${
                      isToday ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'text-slate-700'
                    }`}>
                      {dayNum}
                    </span>
                    {dayMeetings.length > 0 && (
                      <span className="text-[10px] font-mono text-slate-400 font-semibold">{dayMeetings.length} mtg</span>
                    )}
                  </div>

                  {/* Meeting Badges inside Day Cell */}
                  <div className="space-y-1">
                    {dayMeetings.map((m) => (
                      <div
                        key={m.id}
                        onClick={() => onSelectMeeting(m)}
                        className={`p-1.5 rounded-lg text-[10px] cursor-pointer transition-all truncate border shadow-2xs ${
                          m.status === 'Approved' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' :
                          m.status === 'Pending Approval' ? 'bg-amber-50 border-amber-200 text-amber-900' :
                          'bg-slate-50 border-slate-200 text-slate-700'
                        }`}
                        title={`${m.title} (${m.startTime}-${m.endTime})`}
                      >
                        <div className="font-bold truncate">{m.title}</div>
                        <div className="text-[9px] font-mono text-slate-500 truncate">
                          {m.startTime} • {rooms.find(r => r.id === m.roomId)?.name || 'Venue'}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* List View */}
      {calendarViewMode === 'list' && (
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3">
          <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
            Filtered Meetings Agenda List ({filteredMeetings.length})
          </h3>
          
          <div className="divide-y divide-slate-100">
            {filteredMeetings.map((m) => {
              const room = rooms.find((r) => r.id === m.roomId);
              return (
                <div
                  key={m.id}
                  onClick={() => onSelectMeeting(m)}
                  className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 px-3 rounded-xl transition-colors cursor-pointer"
                >
                  <div className="space-y-1">
                    <div className="flex items-center space-x-2">
                      <span className="font-bold text-slate-900 text-xs">{m.title}</span>
                      <span className="text-[10px] bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-md font-medium">
                        {m.meetingType}
                      </span>
                    </div>
                    <div className="text-[11px] text-slate-500 flex items-center space-x-3">
                      <span className="text-indigo-600 font-mono font-bold">{m.date} ({m.startTime} - {m.endTime})</span>
                      <span>•</span>
                      <span>Venue: {room?.name || 'Custom'}</span>
                    </div>
                  </div>

                  <span className={`px-2.5 py-1 rounded-lg text-[10px] font-bold self-start sm:self-center ${
                    m.status === 'Approved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' :
                    m.status === 'Pending Approval' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                    'bg-slate-100 text-slate-700'
                  }`}>
                    {m.status}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}

    </div>
  );
};

