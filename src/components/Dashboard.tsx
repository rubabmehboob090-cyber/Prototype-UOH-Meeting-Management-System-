import React from 'react';
import { SystemStats, Meeting, Room, User } from '../types/index';
import { 
  Calendar, CheckCircle, Clock, Building, 
  Users, ArrowRight, ShieldCheck, ListTodo, Plus, UserCheck, Lock, FileText 
} from 'lucide-react';
import { getRolePermissions, filterMeetingsForRole } from '../utils/rbac';

interface DashboardProps {
  stats: SystemStats | null;
  meetings: Meeting[];
  rooms: Room[];
  currentUser: User;
  onOpenNewMeeting: () => void;
  onSelectMeeting: (meeting: Meeting) => void;
  onNavigate: (view: string) => void;
  activeClashesCount?: number;
}

export const Dashboard: React.FC<DashboardProps> = ({
  stats,
  meetings,
  rooms,
  currentUser,
  onOpenNewMeeting,
  onSelectMeeting,
  onNavigate,
  activeClashesCount = 0
}) => {
  const permissions = getRolePermissions(currentUser.role);
  const roleMeetings = filterMeetingsForRole(meetings, currentUser);

  const todayStr = new Date().toISOString().split('T')[0];
  const todayMeetings = roleMeetings.filter((m) => m.date === todayStr);
  const pendingMeetings = meetings.filter((m) => m.status === 'Pending Approval');

  // Faculty specific metrics
  const myInvitedCount = roleMeetings.length;
  const myActionItemsCount = stats?.activeActionItems || 0;

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Welcome Banner - Tailored per Role Abstraction */}
      <div className="bg-indigo-600 rounded-2xl p-6 shadow-md relative overflow-hidden text-white">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
          <div>
            <div className="flex items-center space-x-2">
              <span className="text-xs font-bold uppercase tracking-wider text-indigo-200">
                Welcome Back, {currentUser.name}
              </span>
              <span className="text-[10px] bg-indigo-700/80 text-indigo-100 border border-indigo-400/30 px-2.5 py-0.5 rounded-full font-mono font-medium flex items-center space-x-1">
                <span>{currentUser.role}</span>
              </span>
            </div>
            <h2 className="text-xl sm:text-2xl font-bold tracking-tight text-white mt-1">
              {permissions.portalTitle}
            </h2>
            <p className="text-xs text-indigo-100 mt-1 max-w-2xl leading-relaxed">
              {permissions.portalDescription}
            </p>
          </div>

          <div className="flex items-center space-x-3">
            {permissions.canCreateMeeting ? (
              <button
                onClick={onOpenNewMeeting}
                className="bg-white hover:bg-indigo-50 text-indigo-600 px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm flex items-center space-x-2 transition-all cursor-pointer"
              >
                <Plus className="w-4 h-4" />
                <span>Request New Meeting</span>
              </button>
            ) : (
              <div className="bg-indigo-700/60 border border-indigo-400/30 text-indigo-100 px-3.5 py-2 rounded-xl text-xs font-medium flex items-center space-x-2">
                <Lock className="w-3.5 h-3.5 text-indigo-200" />
                <span>Faculty View: Attendee Access Only</span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Stats Metric Grid - Abstracted based on Role */}
      {currentUser.role === 'Faculty/Staff' ? (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">My Invited Meetings</span>
              <Calendar className="w-4 h-4 text-indigo-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{myInvitedCount}</div>
            <div className="text-[10px] text-slate-500 mt-1">Assigned Meetings</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Today's Schedule</span>
              <Clock className="w-4 h-4 text-indigo-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{todayMeetings.length}</div>
            <div className="text-[10px] text-indigo-600 mt-1 font-semibold">Scheduled Today</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Assigned Action Items</span>
              <ListTodo className="w-4 h-4 text-purple-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{myActionItemsCount}</div>
            <div className="text-[10px] text-slate-500 mt-1">Post-Meeting Tasks</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Published MoM Records</span>
              <FileText className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{meetings.length}</div>
            <div className="text-[10px] text-slate-500 mt-1">Available to Read</div>
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Total Scheduled</span>
              <Calendar className="w-4 h-4 text-indigo-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{stats?.totalMeetings || meetings.length}</div>
            <div className="text-[10px] text-slate-500 mt-1">Confirmed & Archived</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Pending Approvals</span>
              <Clock className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{stats?.pendingApprovals || pendingMeetings.length}</div>
            <div className="text-[10px] text-amber-600 mt-1 font-semibold">Awaiting Authority Review</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Room Utilization</span>
              <Building className="w-4 h-4 text-indigo-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{stats?.roomUtilizationRate || 42}%</div>
            <div className="text-[10px] text-slate-500 mt-1">Across Campus Venues</div>
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-bold">Active Action Items</span>
              <ListTodo className="w-4 h-4 text-purple-600" />
            </div>
            <div className="text-2xl font-bold text-slate-900">{stats?.activeActionItems || 5}</div>
            <div className="text-[10px] text-slate-500 mt-1">Assigned Tasks</div>
          </div>
        </div>
      )}

      {/* Automated Clash Engine Alert Banner */}
      {activeClashesCount > 0 && (
        <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-rose-100 border border-rose-300 text-rose-700 flex items-center justify-center flex-shrink-0">
              <ShieldCheck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h4 className="font-bold text-sm text-rose-900">
                  Automated Clash Detection: {activeClashesCount} Conflict{activeClashesCount > 1 ? 's' : ''} Detected
                </h4>
                <span className="text-[10px] bg-rose-200 text-rose-900 font-bold px-2 py-0.5 rounded-full">
                  Action Required
                </span>
              </div>
              <p className="text-xs text-rose-700 mt-0.5">
                Overlapping room double-bookings or participant schedule conflicts exist across campus schedules.
              </p>
            </div>
          </div>

          <button
            onClick={() => onNavigate('clash-detector')}
            className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs flex items-center space-x-1.5 transition-colors cursor-pointer self-start sm:self-auto"
          >
            <span>Launch Clash Resolver</span>
            <ArrowRight className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Main Grid: Today's Timeline + Role-Specific Right Column */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Left Column (2 cols): Today's & Upcoming Schedule */}
        <div className="lg:col-span-2 space-y-4">
          <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
            
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-sm font-bold text-slate-800 flex items-center space-x-2">
                  <Calendar className="w-4 h-4 text-indigo-600" />
                  <span>
                    {currentUser.role === 'Faculty/Staff' ? "My Attending Meetings & Schedule" : "Today's Meetings & Schedule"}
                  </span>
                </h3>
                <p className="text-[11px] text-slate-500">{todayStr} • University of Haripur Campus</p>
              </div>
              <button
                onClick={() => onNavigate('calendar')}
                className="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1 cursor-pointer"
              >
                <span>View Full Calendar</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>

            {roleMeetings.length === 0 ? (
              <div className="text-center py-8 text-slate-400 text-xs">
                No meetings assigned or scheduled for you at this time.
              </div>
            ) : (
              <div className="space-y-3">
                {roleMeetings.slice(0, 5).map((mtg) => {
                  const room = rooms.find((r) => r.id === mtg.roomId);
                  const myParticipantInfo = mtg.participants.find((p) => p.userId === currentUser.id);

                  return (
                    <div
                      key={mtg.id}
                      onClick={() => onSelectMeeting(mtg)}
                      className="bg-slate-50 border border-slate-200 hover:border-indigo-400 rounded-xl p-3.5 transition-all cursor-pointer space-y-2 border-l-4 border-l-indigo-500"
                    >
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="flex items-center space-x-2">
                            <span className="font-bold text-slate-900 text-xs">{mtg.title}</span>
                            {mtg.isUniversityWideEvent && (
                              <span className="bg-amber-100 text-amber-800 text-[9px] font-bold px-1.5 py-0.2 rounded border border-amber-200">
                                Statutory Event
                              </span>
                            )}
                            {myParticipantInfo && (
                              <span className="bg-indigo-100 text-indigo-800 text-[9px] font-bold px-1.5 py-0.2 rounded border border-indigo-200">
                                Role: {myParticipantInfo.roleInMeeting}
                              </span>
                            )}
                          </div>
                          <p className="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{mtg.description}</p>
                        </div>
                        
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                          mtg.status === 'Approved' ? 'bg-green-100 text-green-700 border border-green-200' :
                          mtg.status === 'Pending Approval' ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                          'bg-slate-100 text-slate-600'
                        }`}>
                          {mtg.status}
                        </span>
                      </div>

                      <div className="flex flex-wrap items-center gap-3 text-[10px] text-slate-600 pt-1 border-t border-slate-200/60">
                        <span className="flex items-center space-x-1 font-mono text-indigo-700 font-semibold">
                          <Clock className="w-3 h-3" />
                          <span>{mtg.date} ({mtg.startTime} - {mtg.endTime})</span>
                        </span>
                        <span className="flex items-center space-x-1 text-slate-500">
                          <Building className="w-3 h-3 text-slate-400" />
                          <span>{room?.name || 'Custom Venue'}</span>
                        </span>
                        <span className="flex items-center space-x-1 text-slate-500">
                          <Users className="w-3 h-3" />
                          <span>{mtg.participants.length} Participants</span>
                        </span>
                        <span className="text-slate-500">
                          Mode: <strong className="text-slate-700">{mtg.mode}</strong>
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}

          </div>
        </div>

        {/* Right Column (1 col): Adapted according to Role Abstraction */}
        <div className="space-y-4">
          
          {/* If Faculty: Show Assigned Action Items & Department Info */}
          {currentUser.role === 'Faculty/Staff' ? (
            <>
              <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                  <h3 className="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                    <ListTodo className="w-4 h-4 text-purple-600" />
                    <span>My Assigned Tasks</span>
                  </h3>
                  <button
                    onClick={() => onNavigate('action-items')}
                    className="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold"
                  >
                    View All
                  </button>
                </div>

                <div className="space-y-2">
                  <div className="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs space-y-1">
                    <div className="font-bold text-slate-800">Finalize BS CS Curriculum Proposal</div>
                    <div className="text-[10px] text-slate-500">Origin: Academic Council Session</div>
                    <div className="text-[10px] font-mono text-indigo-600 font-bold mt-1">Due: 2026-08-20</div>
                  </div>
                  <div className="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs space-y-1">
                    <div className="font-bold text-slate-800">Submit Midterm Exam Schedule Draft</div>
                    <div className="text-[10px] text-slate-500">Origin: Departmental Meeting</div>
                    <div className="text-[10px] font-mono text-amber-600 font-bold mt-1">Due: 2026-08-15</div>
                  </div>
                </div>
              </div>

              <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                  <h3 className="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                    <UserCheck className="w-4 h-4 text-indigo-600" />
                    <span>My Department Info</span>
                  </h3>
                </div>
                <div className="text-xs space-y-1.5 text-slate-600">
                  <div className="font-bold text-slate-800">Department of CS & IT</div>
                  <div>HOD: Dr. Tariq Mahmood</div>
                  <div>Office Ext: Ext. 620</div>
                  <div className="text-[10px] text-slate-400 mt-2">
                    Note: Meeting requests must be submitted through your HOD or Office Admin.
                  </div>
                </div>
              </div>
            </>
          ) : (
            <>
              {/* Pending Approvals Widget for Approvers */}
              {permissions.canApproveMeetings && (
                <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                  <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 className="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                      <ShieldCheck className="w-4 h-4 text-amber-500" />
                      <span>Pending Approvals ({pendingMeetings.length})</span>
                    </h3>
                    <button
                      onClick={() => onNavigate('approvals')}
                      className="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold"
                    >
                      Review All
                    </button>
                  </div>

                  {pendingMeetings.length === 0 ? (
                    <p className="text-[11px] text-slate-500 text-center py-4">No pending approvals required.</p>
                  ) : (
                    <div className="space-y-2">
                      {pendingMeetings.slice(0, 3).map((pm) => (
                        <div key={pm.id} className="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs space-y-1">
                          <div className="font-bold text-slate-800 truncate">{pm.title}</div>
                          <div className="text-[10px] text-slate-500">
                            {pm.date} • {pm.startTime}-{pm.endTime}
                          </div>
                          <button
                            onClick={() => onNavigate('approvals')}
                            className="w-full mt-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 py-1 rounded-lg text-[10px] font-bold cursor-pointer"
                          >
                            Review Request
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Room Availability Quick Widget */}
              <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                  <h3 className="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                    <Building className="w-4 h-4 text-indigo-600" />
                    <span>UoH Campus Rooms</span>
                  </h3>
                  {permissions.canManageRooms && (
                    <button
                      onClick={() => onNavigate('rooms')}
                      className="text-[10px] text-indigo-600 font-bold"
                    >
                      Manage
                    </button>
                  )}
                </div>

                <div className="space-y-2">
                  {rooms.slice(0, 4).map((r) => (
                    <div key={r.id} className="flex items-center justify-between p-2 rounded-xl bg-slate-50 text-xs border border-slate-100">
                      <div>
                        <div className="font-bold text-slate-800 text-[11px]">{r.name}</div>
                        <div className="text-[9px] text-slate-500">Capacity: {r.capacity} seats</div>
                      </div>
                      <span className="px-2 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-700 border border-green-200">
                        Active
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </>
          )}

        </div>

      </div>

    </div>
  );
};

