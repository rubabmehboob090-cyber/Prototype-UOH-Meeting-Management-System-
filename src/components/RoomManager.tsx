import React, { useState } from 'react';
import { Room, Meeting, User } from '../types/index.ts';
import { createRoom } from '../services/api.ts';
import { Building, Users, Shield, Plus, Lock } from 'lucide-react';
import { getRolePermissions } from '../utils/rbac.ts';

interface RoomManagerProps {
  rooms: Room[];
  meetings: Meeting[];
  onRefreshRooms: () => void;
  onOpenBookRoom: (roomId: string) => void;
  currentUser?: User;
}

export const RoomManager: React.FC<RoomManagerProps> = ({
  rooms,
  meetings,
  onRefreshRooms,
  onOpenBookRoom,
  currentUser
}) => {
  const permissions = currentUser ? getRolePermissions(currentUser.role) : { canCreateMeeting: true };
  const [showAddModal, setShowAddModal] = useState(false);
  const [name, setName] = useState('');
  const [building, setBuilding] = useState('');
  const [capacity, setCapacity] = useState(30);
  const [facilitiesInput, setFacilitiesInput] = useState('Air Conditioning, Multimedia Projector, Wi-Fi');
  const [requiresApproval, setRequiresApproval] = useState(true);

  const handleCreateRoom = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    try {
      await createRoom({
        name,
        building: building || 'Main Campus',
        capacity: Number(capacity),
        facilities: facilitiesInput.split(',').map((f) => f.trim()),
        requiresApproval,
        isActive: true
      });
      setShowAddModal(false);
      setName('');
      onRefreshRooms();
    } catch (e) {
      console.error(e);
    }
  };

  const todayStr = new Date().toISOString().split('T')[0];

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
              <Building className="w-5 h-5" />
            </div>
            <h2 className="text-lg font-bold tracking-tight text-slate-900">University Venues & Room Booking Status</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Senate halls, auditoriums, video conference rooms, and departmental seminar spaces.
          </p>
        </div>

        {permissions.canCreateMeeting && (
          <button
            onClick={() => setShowAddModal(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm flex items-center space-x-1.5 transition-colors cursor-pointer self-start sm:self-auto"
          >
            <Plus className="w-4 h-4" />
            <span>Add New Room</span>
          </button>
        )}
      </div>

      {/* Room Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {rooms.map((r) => {
          // Meetings today in this room
          const roomMeetingsToday = meetings.filter((m) => m.roomId === r.id && m.date === todayStr && m.status === 'Approved');

          return (
            <div 
              key={r.id} 
              className="bg-white border border-slate-200 hover:border-slate-300 rounded-2xl p-5 shadow-sm hover:shadow transition-all flex flex-col justify-between space-y-4"
            >
              <div>
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="text-base font-bold text-slate-900">{r.name}</h3>
                    <p className="text-xs text-slate-500">{r.building}</p>
                  </div>
                  <span className="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] font-bold px-2 py-0.5 rounded-lg flex items-center space-x-1">
                    <Users className="w-3 h-3 text-indigo-600" />
                    <span>Cap: {r.capacity}</span>
                  </span>
                </div>

                {/* Facilities Badges */}
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {r.facilities.map((fac, i) => (
                    <span 
                      key={i} 
                      className="bg-slate-50 text-slate-600 text-[10px] font-medium px-2 py-0.5 rounded-lg border border-slate-200"
                    >
                      {fac}
                    </span>
                  ))}
                </div>

                {/* Today's Reservations */}
                <div className="mt-4 pt-3 border-t border-slate-100">
                  <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                    Today's Reservations ({roomMeetingsToday.length})
                  </div>
                  {roomMeetingsToday.length === 0 ? (
                    <p className="text-[11px] text-emerald-600 font-medium italic">Available all day</p>
                  ) : (
                    <div className="space-y-1.5">
                      {roomMeetingsToday.map((m) => (
                        <div key={m.id} className="bg-slate-50 p-2 rounded-xl text-[11px] border border-slate-200">
                          <div className="font-bold text-slate-800 truncate">{m.title}</div>
                          <div className="text-[10px] font-mono font-semibold text-indigo-600">{m.startTime} - {m.endTime}</div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                <span className="text-[10px] text-slate-500 flex items-center space-x-1">
                  {r.requiresApproval ? (
                    <span className="text-amber-700 font-medium flex items-center space-x-1">
                      <Shield className="w-3 h-3 text-amber-600" />
                      <span>Approval Required</span>
                    </span>
                  ) : (
                    <span className="text-emerald-700 font-medium">Direct Booking</span>
                  )}
                </span>

                <button
                  onClick={() => onOpenBookRoom(r.id)}
                  className="bg-slate-50 hover:bg-indigo-50 text-indigo-700 border border-slate-200 hover:border-indigo-200 px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition-colors"
                >
                  Book This Room
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Add Room Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-lg p-6 shadow-2xl space-y-4 text-xs text-slate-900">
            <h3 className="text-base font-bold text-slate-900">Add New Campus Venue / Room</h3>

            <form onSubmit={handleCreateRoom} className="space-y-3">
              <div>
                <label className="block text-slate-700 font-bold mb-1">Room Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Conference Room 301"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-bold mb-1">Building Location</label>
                <input
                  type="text"
                  placeholder="e.g. Admin Block, 2nd Floor"
                  value={building}
                  onChange={(e) => setBuilding(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-bold mb-1">Seating Capacity</label>
                <input
                  type="number"
                  value={capacity}
                  onChange={(e) => setCapacity(Number(e.target.value))}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-bold mb-1">Facilities (Comma Separated)</label>
                <input
                  type="text"
                  value={facilitiesInput}
                  onChange={(e) => setFacilitiesInput(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-indigo-600 font-medium"
                />
              </div>

              <div className="flex items-center space-x-2 pt-2">
                <input
                  type="checkbox"
                  id="reqApp"
                  checked={requiresApproval}
                  onChange={(e) => setRequiresApproval(e.target.checked)}
                  className="rounded bg-white border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                />
                <label htmlFor="reqApp" className="text-slate-700 font-medium">Requires Room Manager / Authority Approval</label>
              </div>

              <div className="pt-4 flex justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold cursor-pointer shadow-sm"
                >
                  Save Room
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};

