import React, { useState, useEffect } from 'react';
import { User, Department, Room, Meeting, SystemStats } from './types/index';
import { 
  fetchUsers, fetchDepartments, fetchRooms, fetchMeetings, fetchStats 
} from './services/api';

import { Navbar } from './components/Navbar';
import { Sidebar } from './components/Sidebar';
import { Dashboard } from './components/Dashboard';
import { MeetingRequestModal } from './components/MeetingRequestModal';
import { ApprovalQueue } from './components/ApprovalQueue';
import { CalendarView } from './components/CalendarView';
import { RoomManager } from './components/RoomManager';
import { ActionItemsTracker } from './components/ActionItemsTracker';
import { MeetingDetailModal } from './components/MeetingDetailModal';
import { ReportsView } from './components/ReportsView';
import { AuditLogView } from './components/AuditLogView';

export default function App() {
  const [users, setUsers] = useState<User[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [rooms, setRooms] = useState<Room[]>([]);
  const [meetings, setMeetings] = useState<Meeting[]>([]);
  const [stats, setStats] = useState<SystemStats | null>(null);

  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [activeView, setActiveView] = useState<string>('dashboard');

  // Modal States
  const [isNewMeetingModalOpen, setIsNewMeetingModalOpen] = useState(false);
  const [modalRoomId, setModalRoomId] = useState<string | undefined>(undefined);
  const [selectedMeetingDetail, setSelectedMeetingDetail] = useState<Meeting | null>(null);

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadAllData();
  }, []);

  const loadAllData = async () => {
    setLoading(true);
    try {
      const [uList, dList, rList, mList, sData] = await Promise.all([
        fetchUsers(),
        fetchDepartments(),
        fetchRooms(),
        fetchMeetings(),
        fetchStats()
      ]);

      setUsers(uList);
      setDepartments(dList);
      setRooms(rList);
      setMeetings(mList);
      setStats(sData);

      if (!currentUser && uList.length > 0) {
        // Default to Dr. Shafiq Ahmed (Super Admin / Assistant Registrar)
        setCurrentUser(uList[0]);
      }
    } catch (e) {
      console.error('Failed to load initial data:', e);
    } finally {
      setLoading(false);
    }
  };

  const reloadMeetingsAndStats = async () => {
    try {
      const [mList, sData] = await Promise.all([fetchMeetings(), fetchStats()]);
      setMeetings(mList);
      setStats(sData);
    } catch (e) {
      console.error(e);
    }
  };

  const reloadRooms = async () => {
    try {
      const rList = await fetchRooms();
      setRooms(rList);
    } catch (e) {
      console.error(e);
    }
  };

  const handleOpenBookRoom = (roomId: string) => {
    setModalRoomId(roomId);
    setIsNewMeetingModalOpen(true);
  };

  if (loading || !currentUser) {
    return (
      <div className="min-h-screen bg-[#F1F5F9] flex flex-col items-center justify-center text-slate-900 p-4">
        <div className="w-12 h-12 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white text-xl animate-pulse mb-3 shadow-md">
          U
        </div>
        <p className="text-xs text-slate-500 font-bold">Loading UoH Meeting Management System...</p>
      </div>
    );
  }

  const pendingApprovalsCount = meetings.filter((m) => m.status === 'Pending Approval').length;

  return (
    <div className="min-h-screen bg-[#F1F5F9] text-slate-900 flex flex-col font-sans selection:bg-indigo-600 selection:text-white">
      
      {/* Top Navigation */}
      <Navbar
        users={users}
        currentUser={currentUser}
        onUserChange={(u) => setCurrentUser(u)}
        pendingApprovalsCount={pendingApprovalsCount}
        onOpenNewMeeting={() => {
          setModalRoomId(undefined);
          setIsNewMeetingModalOpen(true);
        }}
        onNavigate={(v) => setActiveView(v)}
        activeView={activeView}
      />

      {/* Main Body Layout */}
      <div className="flex-1 flex flex-col md:flex-row max-w-7xl w-full mx-auto">
        
        {/* Sidebar */}
        <Sidebar
          activeView={activeView}
          currentUser={currentUser}
          onNavigate={(v) => {
            if (v === 'schedule') {
              setModalRoomId(undefined);
              setIsNewMeetingModalOpen(true);
            } else {
              setActiveView(v);
            }
          }}
          pendingApprovalsCount={pendingApprovalsCount}
        />

        {/* Content View Container */}
        <main className="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
          {activeView === 'dashboard' && (
            <Dashboard
              stats={stats}
              meetings={meetings}
              rooms={rooms}
              currentUser={currentUser}
              onOpenNewMeeting={() => {
                setModalRoomId(undefined);
                setIsNewMeetingModalOpen(true);
              }}
              onSelectMeeting={(m) => setSelectedMeetingDetail(m)}
              onNavigate={(v) => setActiveView(v)}
            />
          )}

          {activeView === 'approvals' && (
            <ApprovalQueue
              currentUser={currentUser}
              onRefreshStats={reloadMeetingsAndStats}
            />
          )}

          {activeView === 'calendar' && (
            <CalendarView
              meetings={meetings}
              rooms={rooms}
              departments={departments}
              currentUser={currentUser}
              onSelectMeeting={(m) => setSelectedMeetingDetail(m)}
              onOpenNewMeeting={() => {
                setModalRoomId(undefined);
                setIsNewMeetingModalOpen(true);
              }}
            />
          )}

          {activeView === 'rooms' && (
            <RoomManager
              rooms={rooms}
              meetings={meetings}
              onRefreshRooms={reloadRooms}
              onOpenBookRoom={handleOpenBookRoom}
              currentUser={currentUser}
            />
          )}

          {activeView === 'action-items' && (
            <ActionItemsTracker
              users={users}
              meetings={meetings}
              currentUser={currentUser}
            />
          )}

          {activeView === 'minutes' && (
            <div className="space-y-4 text-slate-900">
              <h2 className="text-xl font-bold tracking-tight">Minutes of Meeting (MoM) & Agendas Records</h2>
              <p className="text-xs text-slate-500">Select any meeting from the master schedule to view or draft official MoM.</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {meetings.map((m) => (
                  <div
                    key={m.id}
                    onClick={() => setSelectedMeetingDetail(m)}
                    className="p-4 bg-white border border-slate-200 rounded-2xl hover:border-indigo-300 hover:shadow-xs cursor-pointer text-xs space-y-1 transition-all"
                  >
                    <div className="font-bold text-slate-900">{m.title}</div>
                    <div className="text-slate-500 font-medium">{m.date} • {m.meetingType}</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeView === 'reports' && (
            <ReportsView
              rooms={rooms}
              meetings={meetings}
              departments={departments}
            />
          )}

          {activeView === 'audit-logs' && (
            <AuditLogView />
          )}
        </main>

      </div>

      {/* New Meeting Request Modal */}
      <MeetingRequestModal
        isOpen={isNewMeetingModalOpen}
        onClose={() => setIsNewMeetingModalOpen(false)}
        users={users}
        departments={departments}
        rooms={rooms}
        currentUser={currentUser}
        onMeetingCreated={reloadMeetingsAndStats}
        initialRoomId={modalRoomId}
      />

      {/* Meeting Detail Modal */}
      <MeetingDetailModal
        meeting={selectedMeetingDetail}
        onClose={() => setSelectedMeetingDetail(null)}
        users={users}
        rooms={rooms}
        currentUser={currentUser}
        onMeetingUpdated={reloadMeetingsAndStats}
      />

    </div>
  );
}
