import React, { useState, useEffect } from 'react';
import { User } from '../types/index';
import { fetchApprovals, updateMeetingStatus } from '../services/api';
import { ShieldCheck, CheckCircle2, XCircle, AlertTriangle, Clock, MapPin, UserCheck } from 'lucide-react';

interface ApprovalQueueProps {
  currentUser: User;
  onRefreshStats: () => void;
}

export const ApprovalQueue: React.FC<ApprovalQueueProps> = ({ currentUser, onRefreshStats }) => {
  const [approvalsQueue, setApprovalsQueue] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [commentsMap, setCommentsMap] = useState<Record<string, string>>({});
  const [actioningId, setActioningId] = useState<string | null>(null);

  useEffect(() => {
    loadQueue();
  }, [currentUser.id, currentUser.role, currentUser.departmentId]);

  const loadQueue = async () => {
    setLoading(true);
    try {
      const data = await fetchApprovals({
        userId: currentUser.id,
        role: currentUser.role,
        departmentId: currentUser.departmentId
      });
      setApprovalsQueue(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleDecision = async (meetingId: string, status: 'Approved' | 'Rejected') => {
    setActioningId(meetingId);
    try {
      const comment = commentsMap[meetingId] || '';
      await updateMeetingStatus(meetingId, status, comment, currentUser.id);
      await loadQueue();
      onRefreshStats();
    } catch (e) {
      console.error(e);
    } finally {
      setActioningId(null);
    }
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <div className="flex items-center space-x-2">
            <ShieldCheck className="w-6 h-6 text-indigo-600" />
            <h2 className="text-xl font-bold tracking-tight text-slate-900">Meeting Approval Workflow Queue</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Review meeting requests, inspect room and participant double-booking conflicts, and authorize reservations.
          </p>
        </div>
        <button
          onClick={loadQueue}
          className="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 cursor-pointer"
        >
          Refresh Queue
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-500 text-xs font-medium">Loading approval requests...</div>
      ) : approvalsQueue.length === 0 ? (
        <div className="bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-500 space-y-2 shadow-sm">
          <CheckCircle2 className="w-10 h-10 text-green-500 mx-auto" />
          <h3 className="text-sm font-bold text-slate-800">No Pending Approvals</h3>
          <p className="text-xs max-w-md mx-auto">
            All submitted meeting requests and room reservations have been processed.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {approvalsQueue.map(({ meeting, approvalRecord, requester, room, conflictAnalysis }) => (
            <div 
              key={meeting.id} 
              className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4"
            >
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-bold text-slate-900">{meeting.title}</span>
                    <span className="bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-bold px-2 py-0.5 rounded-full">
                      {meeting.meetingType}
                    </span>
                    {approvalRecord?.approverRole && (
                      <span className="bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold px-2 py-0.5 rounded-full">
                        Assigned Approver: {approvalRecord.approverRole}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5">{meeting.description || 'No description provided.'}</p>
                </div>

                <div className="text-left md:text-right text-xs">
                  <div className="text-slate-600 font-medium">Requested By: <strong className="text-slate-900">{requester?.name}</strong></div>
                  <div className="text-[10px] text-slate-400">{requester?.designation} • {requester?.email}</div>
                </div>
              </div>

              {/* Schedule & Venue Metadata */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl text-xs border border-slate-200">
                <div>
                  <div className="text-[10px] text-slate-500">Date & Time</div>
                  <div className="font-bold text-indigo-600 font-mono mt-0.5">
                    {meeting.date} ({meeting.startTime} - {meeting.endTime})
                  </div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500">Requested Venue</div>
                  <div className="font-semibold text-slate-800 mt-0.5">{room?.name || 'Custom Venue'}</div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500">Meeting Mode</div>
                  <div className="font-semibold text-slate-800 mt-0.5">{meeting.mode}</div>
                </div>

                <div>
                  <div className="text-[10px] text-slate-500">Invited Participants</div>
                  <div className="font-semibold text-slate-800 mt-0.5">{meeting.participants.length} Users</div>
                </div>
              </div>

              {/* Conflict Analysis Preview for Approver */}
              {conflictAnalysis?.hasConflict ? (
                <div className="bg-amber-50 border border-amber-200 p-3 rounded-xl text-xs space-y-1">
                  <div className="flex items-center space-x-1.5 text-amber-800 font-bold">
                    <AlertTriangle className="w-4 h-4 text-amber-600" />
                    <span>Conflict Alert ({conflictAnalysis.conflicts.length})</span>
                  </div>
                  {conflictAnalysis.conflicts.map((c: any, i: number) => (
                    <div key={i} className="text-amber-900 text-[11px] pl-5 list-disc">
                      • {c.description}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="bg-green-50 border border-green-200 p-2.5 rounded-xl text-xs text-green-800 flex items-center space-x-2">
                  <CheckCircle2 className="w-4 h-4 text-green-600" />
                  <span>Conflict Engine Check: Room and participants are 100% available with zero conflicts.</span>
                </div>
              )}

              {/* Decision Comment & Buttons */}
              <div className="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                <input
                  type="text"
                  placeholder="Optional approval comments or rejection reason..."
                  value={commentsMap[meeting.id] || ''}
                  onChange={(e) => setCommentsMap({ ...commentsMap, [meeting.id]: e.target.value })}
                  className="w-full sm:flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:border-indigo-500"
                />

                <div className="flex items-center space-x-2 w-full sm:w-auto">
                  <button
                    onClick={() => handleDecision(meeting.id, 'Rejected')}
                    disabled={actioningId === meeting.id}
                    className="flex-1 sm:flex-none bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-4 py-1.5 rounded-xl text-xs font-bold flex items-center justify-center space-x-1 transition-colors cursor-pointer"
                  >
                    <XCircle className="w-4 h-4" />
                    <span>Reject</span>
                  </button>

                  <button
                    onClick={() => handleDecision(meeting.id, 'Approved')}
                    disabled={actioningId === meeting.id}
                    className="flex-1 sm:flex-none bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-1.5 rounded-xl text-xs font-bold shadow-sm flex items-center justify-center space-x-1 transition-colors cursor-pointer"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    <span>Approve & Finalize Room</span>
                  </button>
                </div>
              </div>

            </div>
          ))}
        </div>
      )}

    </div>
  );
};
