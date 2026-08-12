import React from 'react';
import { Room, Meeting, Department } from '../types/index.ts';
import { BarChart3, Building, FileText, Download } from 'lucide-react';

interface ReportsViewProps {
  rooms: Room[];
  meetings: Meeting[];
  departments: Department[];
}

export const ReportsView: React.FC<ReportsViewProps> = ({ rooms, meetings, departments }) => {
  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
              <BarChart3 className="w-5 h-5" />
            </div>
            <h2 className="text-lg font-bold tracking-tight text-slate-900">Administrative Reports & Room Utilization</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Campus venue occupancy rates, departmental meeting frequencies, and compliance statistics.
          </p>
        </div>

        <button
          onClick={() => alert('Exporting UoH Official Meeting Summary Report (PDF/Excel)...')}
          className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm flex items-center space-x-1.5 transition-colors cursor-pointer self-start sm:self-auto"
        >
          <Download className="w-4 h-4" />
          <span>Export Official Report</span>
        </button>
      </div>

      {/* Room Occupancy Analytics Table */}
      <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
        <h3 className="text-sm font-bold text-slate-900 flex items-center space-x-2">
          <Building className="w-4 h-4 text-indigo-600" />
          <span>Campus Venue Occupancy Breakdown</span>
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-y border-slate-200">
              <tr>
                <th className="p-3">Venue Name</th>
                <th className="p-3">Building</th>
                <th className="p-3">Capacity</th>
                <th className="p-3">Total Meetings Held</th>
                <th className="p-3">Est. Utilization Rate</th>
                <th className="p-3">Approval Type</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {rooms.map((r) => {
                const roomMeetingsCount = meetings.filter((m) => m.roomId === r.id).length;
                const mockUtilization = Math.min(100, Math.round((roomMeetingsCount / 5) * 65) + 20);

                return (
                  <tr key={r.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="p-3 font-bold text-slate-900">{r.name}</td>
                    <td className="p-3 text-slate-500">{r.building}</td>
                    <td className="p-3 font-mono font-medium">{r.capacity} seats</td>
                    <td className="p-3 font-bold text-indigo-600">{roomMeetingsCount} Sessions</td>
                    <td className="p-3">
                      <div className="flex items-center space-x-2">
                        <div className="w-24 bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                          <div 
                            className="bg-indigo-600 h-full rounded-full" 
                            style={{ width: `${mockUtilization}%` }}
                          />
                        </div>
                        <span className="font-mono text-[10px] font-bold text-slate-700">{mockUtilization}%</span>
                      </div>
                    </td>
                    <td className="p-3 text-[10px] font-medium">
                      {r.requiresApproval ? (
                        <span className="text-amber-700 font-bold">Approval Required</span>
                      ) : (
                        <span className="text-emerald-700 font-bold">Direct Booking</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Departmental Activity Analytics */}
      <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
        <h3 className="text-sm font-bold text-slate-900 flex items-center space-x-2">
          <FileText className="w-4 h-4 text-indigo-600" />
          <span>Meetings Hosted by Department</span>
        </h3>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {departments.slice(0, 6).map((d) => {
            const count = meetings.filter((m) => m.departmentId === d.id).length;
            return (
              <div key={d.id} className="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                <div>
                  <div className="font-bold text-slate-900 text-xs">{d.name}</div>
                  <div className="text-[10px] text-slate-500">{d.category} Office</div>
                </div>
                <span className="bg-indigo-50 text-indigo-700 font-bold text-xs px-2.5 py-1 rounded-lg border border-indigo-100">
                  {count} Meetings
                </span>
              </div>
            );
          })}
        </div>
      </div>

    </div>
  );
};

