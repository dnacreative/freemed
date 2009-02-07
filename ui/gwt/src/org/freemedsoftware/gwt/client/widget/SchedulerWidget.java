/*
 * $Id$
 *
 * Authors:
 *      Jeff Buchbinder <jeff@freemedsoftware.org>
 *
 * FreeMED Electronic Medical Record and Practice Management System
 * Copyright (C) 1999-2009 FreeMED Software Foundation
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */

package org.freemedsoftware.gwt.client.widget;

import java.io.Serializable;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.GregorianCalendar;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;

import org.freemedsoftware.gwt.client.CurrentState;
import org.freemedsoftware.gwt.client.JsonUtil;
import org.freemedsoftware.gwt.client.Util;
import org.freemedsoftware.gwt.client.WidgetInterface;
import org.freemedsoftware.gwt.client.Util.ProgramMode;

import com.google.gwt.http.client.Request;
import com.google.gwt.http.client.RequestBuilder;
import com.google.gwt.http.client.RequestCallback;
import com.google.gwt.http.client.RequestException;
import com.google.gwt.http.client.Response;
import com.google.gwt.http.client.URL;
import com.google.gwt.i18n.client.DateTimeFormat;
import com.google.gwt.json.client.JSONParser;
import com.google.gwt.user.client.Window;
import com.google.gwt.user.client.WindowResizeListener;
import com.google.gwt.user.client.ui.Button;
import com.google.gwt.user.client.ui.CheckBox;
import com.google.gwt.user.client.ui.ClickListener;
import com.google.gwt.user.client.ui.DialogBox;
import com.google.gwt.user.client.ui.DockPanel;
import com.google.gwt.user.client.ui.FlexTable;
import com.google.gwt.user.client.ui.HTML;
import com.google.gwt.user.client.ui.HorizontalPanel;
import com.google.gwt.user.client.ui.KeyboardListener;
import com.google.gwt.user.client.ui.Label;
import com.google.gwt.user.client.ui.TextArea;
import com.google.gwt.user.client.ui.Widget;

import eu.future.earth.gwt.client.DateEditFieldWithPicker;
import eu.future.earth.gwt.client.TimeBox;
import eu.future.earth.gwt.client.date.AbstractWholeDayField;
import eu.future.earth.gwt.client.date.BaseDateRenderer;
import eu.future.earth.gwt.client.date.DateEvent;
import eu.future.earth.gwt.client.date.DateEventListener;
import eu.future.earth.gwt.client.date.DatePanel;
import eu.future.earth.gwt.client.date.DatePickerRenderer;
import eu.future.earth.gwt.client.date.DateRenderer;
import eu.future.earth.gwt.client.date.DateUtils;
import eu.future.earth.gwt.client.date.EventController;
import eu.future.earth.gwt.client.date.EventPanel;
import eu.future.earth.gwt.client.date.MultiView;
import eu.future.earth.gwt.client.date.DateEvent.DateEventActions;
import eu.future.earth.gwt.client.date.month.staend.AbstractMonthField;
import eu.future.earth.gwt.client.date.week.staend.AbstractDayField;

public class SchedulerWidget extends WidgetInterface implements
		DateEventListener, WindowResizeListener, ClickListener {

	public class EventData implements Serializable {

		private static final long serialVersionUID = -6586593847569185408L;

		private Date startTime = null;

		private Date endTime = null;

		private String data = null;

		private String id = null;

		private Integer patientId = null;

		private Integer providerId = null;

		private String patientName = null;

		private String providerName = null;

		public EventData() {
			super();
			id = String.valueOf(System.currentTimeMillis());
		}

		public EventData(String currentId) {
			super();
			id = currentId;
		}

		public boolean isAlldayEvent() {
			return endTime == null;
		}

		public void setAsAllDayEvent() {
			endTime = null;
		}

		public String getData() {
			return data;
		}

		public Date getEndTime() {
			return endTime;
		}

		public Date getStartTime() {
			return startTime;
		}

		public Integer getPatientId() {
			return patientId;
		}

		public Integer getProviderId() {
			return providerId;
		}

		public String getPatientName() {
			return patientName;
		}

		public String getProviderName() {
			return providerName;
		}

		public void setData(String data) {
			this.data = data;
		}

		public void setEndTime(Date endTime) {
			this.endTime = endTime;
		}

		public void setStartTime(Date startTime) {
			this.startTime = startTime;
		}

		public void setPatientId(Integer patientId) {
			this.patientId = patientId;
		}

		public void setProviderId(Integer providerId) {
			this.providerId = providerId;
		}

		public void setPatientName(String patientName) {
			this.patientName = patientName;
		}

		public void setProviderName(String providerName) {
			this.providerName = providerName;
		}

		/**
		 * Returns a string representation of the object.
		 * 
		 * @return a string representation of the object.
		 * @todo Implement this java.lang.Object method
		 */
		public String toString() {
			return getStartTime() + "-" + getEndTime() + " data = " + getData();
		}

		/**
		 * This identifier identifies the event in the calendar. All updates and
		 * such rely on an unique id to handle updates correctly. In a
		 * production like situation we would recommend using the key of the
		 * record.
		 * 
		 * @return String - And time based identifier
		 */
		public String getIdentifier() {
			return id;
		}

	}

	public class EventDataDialog extends DialogBox implements KeyboardListener,
			ClickListener {

		private TextArea text = new TextArea();

		private DateEditFieldWithPicker date = new DateEditFieldWithPicker();

		private CheckBox wholeDay = new CheckBox();

		private HorizontalPanel time = new HorizontalPanel();

		private HorizontalPanel timePanel = new HorizontalPanel();

		private TimeBox start = new TimeBox();

		private TimeBox end = new TimeBox();

		private DateEventListener listener = null;

		private PatientWidget patient = null;

		private SupportModuleWidget provider = null;

		private Button cancel = null;

		private Button ok = null;

		private Button delete = null;

		private EventData data = null;

		private DateEventActions command = DateEventActions.ADD;

		private CurrentState state = null;

		public EventDataDialog(DateEventListener newListener, Object newData) {
			this(newListener, newData, DateEventActions.ADD, null);
		}

		public EventDataDialog(DateEventListener newListener, Object newData,
				CurrentState s) {
			this(newListener, newData, DateEventActions.ADD, s);
		}

		public EventDataDialog(DateEventListener newListener, Object newData,
				DateEventActions newCommand, CurrentState s) {
			super();

			state = s;

			setAnimationEnabled(true);
			setStyleName("freemed-SchedulerEventDialog");

			command = newCommand;
			data = (EventData) newData;
			listener = newListener;
			date.setDate(data.getStartTime());
			start.setDate(data.getStartTime());
			if (data.getEndTime() != null) {
				end.setDate(data.getEndTime());
				wholeDay.setChecked(false);
			} else {
				wholeDay.setChecked(true);
			}
			if (newCommand == DateEventActions.ADD) {
				setText("New Appointment");
			} else {
				text.setText((String) data.getData());
				setText("Edit Appointment");
			}

			// VerticalPanel outer = new VerticalPanel();

			final FlexTable table = new FlexTable();

			table.setWidget(0, 0, new Label("Date"));
			table.setWidget(0, 1, date);

			timePanel.add(start);
			timePanel.add(new Label("-"));
			timePanel.add(end);

			time.add(wholeDay);
			wholeDay.addClickListener(this);
			if (data.getEndTime() != null) {
				time.add(timePanel);
			}

			table.setWidget(0, 2, time);
			table.getFlexCellFormatter().setHorizontalAlignment(0, 2,
					HorizontalPanel.ALIGN_LEFT);

			patient = new PatientWidget();
			table.setWidget(1, 0, new Label("Patient"));
			table.setWidget(1, 1, patient);

			provider = new SupportModuleWidget();
			provider.setModuleName("ProviderModule");
			table.setWidget(2, 0, new Label("Provider"));
			if (state == null) {
				JsonUtil.debug("current state not passed to scheduler");
			}
			if (state.getDefaultProvider().intValue() > 0) {
				provider.setValue(state.getDefaultProvider());
			}
			table.setWidget(2, 1, provider);

			table.setWidget(3, 0, new Label("Description"));
			table.setWidget(3, 1, text);
			table.getFlexCellFormatter().setColSpan(1, 1, 2);

			text.addKeyboardListener(this);
			text.setWidth("250px");
			text.setHeight("100px");
			cancel = new Button("Cancel", this);

			cancel.setFocus(true);
			cancel.setAccessKey('c');
			cancel.addClickListener(this);

			ok = new Button("Ok", this);
			ok.setEnabled(false);
			ok.setFocus(true);
			ok.setAccessKey('o');

			final HorizontalPanel button = new HorizontalPanel();
			button.add(ok);

			if (command == DateEventActions.UPDATE) {
				delete = new Button("Delete", this);
				delete.setFocus(true);
				delete.setAccessKey('d');
				delete.addClickListener(this);
				button.add(new HTML(" "));
				button.add(delete);
			}

			button.add(new HTML(" "));
			button.add(cancel);

			table.setWidget(4, 1, button);
			setWidget(table);
			if (text.getText().length() > 1) {
				ok.setEnabled(true);
			} else {
				ok.setEnabled(false);
			}
		}

		public void onKeyDown(Widget widget, char _char, int _int) {

		}

		public void onKeyPress(Widget widget, char key, int _int) {
			if (text.getText().length() > 1) {
				ok.setEnabled(true);
			} else {
				ok.setEnabled(false);
			}
		}

		public void onKeyUp(Widget widget, char _char, int _int) {
		}

		public void onClick(Widget sender) {
			if (sender == wholeDay) {
				if (wholeDay.isChecked()) {
					if (time.getWidgetIndex(timePanel) > -1) {
						time.remove(timePanel);
					}
				} else {
					if (time.getWidgetIndex(timePanel) == -1) {
						time.add(timePanel);
					}
				}
			} else {
				if (sender == ok) {
					if (data == null) {
						data = new EventData();
					}
					if (wholeDay.isChecked()) {
						data.setStartTime(date.getValue());
					} else {
						data.setStartTime(start.getValue(date.getValue()));
						data.setEndTime(end.getValue(date.getValue()));
					}
					data.setData(text.getText());
					final DateEvent newEvent = new DateEvent(this, data);
					newEvent.setCommand(command);
					listener.handleDateEvent(newEvent);
					hide();
				} else {
					if (sender == cancel) {
						hide();
					} else {
						if (data != null && sender != null && sender == delete) {
							final DateEvent newEvent = new DateEvent(this, data);
							newEvent.setCommand(DateEventActions.REMOVE);
							listener.handleDateEvent(newEvent);
							hide();
						} else {
							hide();
						}
					}

				}
			}
		}
	}

	public class DayField extends AbstractDayField {

		// private HorizontalPanel panel = new HorizontalPanel();
		private Label description = new Label();

		private DateTimeFormat format = DateTimeFormat.getFormat("HH:mm");

		public DayField(DateRenderer renderer) {
			super(renderer);
			description.addClickListener(this);
		}

		public Widget createCustom(Serializable theData) {
			if (theData != null) {
				final EventData real = (EventData) theData;
				description.setText((String) real.getData());
				if (real.getEndTime() == null) {
					super.setTitle(format.format(real.getStartTime()));
				} else {
					super.setTitle(format.format(real.getStartTime()) + "-"
							+ format.format(real.getEndTime()));
				}
			}
			return description;
		}

		public void setTitle() {
			final Serializable theData = (Serializable) super.getData();
			final EventData real = (EventData) theData;
			if (real.getEndTime() == null) {
				super.setTitle(format.format(real.getStartTime()));
			} else {
				super.setTitle(format.format(real.getStartTime()) + "-"
						+ format.format(real.getEndTime()));
			}

		}

		public Widget getClickableItem() {
			return description;
		}

		public void repaintPanel() {
		}
	}

	public class MonthField extends AbstractMonthField {

		// private HorizontalPanel panel = new HorizontalPanel();
		private Label description = new Label(); // NOPMD;

		private DateTimeFormat format = DateTimeFormat.getFormat("HH:mm"); // NOPMD;

		public MonthField(DateRenderer renderer) {
			super(renderer);
			description.addClickListener(this);
		}

		/**
		 * createCustom
		 * 
		 * @param theData
		 *            EventData
		 * @return Widget
		 * @todo Implement this
		 *       eu.future.earth.gwt.client.date.month.AbstractMonthField method
		 */
		public Widget createCustom(Serializable theData) {
			if (theData != null) {
				if (theData instanceof EventData) {
					final EventData real = (EventData) theData;
					description.setText(real.getData());
					super.setTitle(format.format(real.getStartTime()));
				} else {
					Window.alert("Programming error " + theData);
				}
			}
			return description;
		}

		public Widget getClickableItem() {
			return description;
		}

		public void repaintPanel() {
		}
	}

	public class WholeDayField extends AbstractWholeDayField {

		public WholeDayField(DateRenderer renderer) {
			super(renderer);
		}

		public void drawTitle(Serializable theData) {
			if (theData != null) {
				if (theData instanceof EventData) {
					final EventData real = (EventData) theData;
					setTitle(real.getData());
				} else {
					Window.alert("Programming error " + theData);
				}
			}

		}

		public void repaintPanel() {
		}
	}

	public class StringPanelRenderer extends BaseDateRenderer implements
			DatePickerRenderer {

		public StringPanelRenderer() {
			super();
		}

		public void createNewAfterClick(Date currentDate,
				DateEventListener listener) {
			final EventData data = new EventData();
			data.setStartTime(currentDate);
			final EventDataDialog dialog = new EventDataDialog(listener, data);
			dialog.show();
			dialog.center();
		}

		public void editAfterClick(Object data, DateEventListener listener) {
			final EventDataDialog dialog = new EventDataDialog(listener, data,
					DateEventActions.UPDATE, state);
			dialog.show();
			dialog.center();
		}

		public void createNewAfterClick(Date currentDate, Date endDate,
				DateEventListener listener) {
			final EventData data = new EventData();
			data.setStartTime(currentDate);
			data.setEndTime(endDate);
			final EventDataDialog dialog = new EventDataDialog(listener, data,
					state);
			dialog.show();
			dialog.center();
		}

		public Widget createPickerPanel(Object newData, int day) {
			return null;
		}

		public boolean supportDayView() {
			return true;
		}

		public boolean supportMonthView() {
			return true;
		}

		public boolean showWholeDayEventView() {
			return true;
		}

		public boolean supportWeekView() {
			return true;
		}

		public boolean enableDragAndDrop() {
			return true;
		}

		public int getEndHour() {
			return 19;
		}

		public int getStartHour() {
			return 7;
		}

		public int showDaysInWeek() {
			return 7;
		}

		public Date getEndTime(Object event) {
			final EventData data = getData(event);
			return data.getEndTime();
		}

		private EventData getData(Object event) {
			if (event instanceof EventData) {
				return (EventData) event;
			} else {
				Window.alert("Not the Right type " + event);
				return null;
			}
		}

		public String getIdentifier(Object event) {
			final EventData data = getData(event);
			return data.getIdentifier();
		}

		public Date getStartTime(Object event) {
			final EventData data = getData(event);
			return data.getStartTime();
		}

		public void setEndTime(Object event, Date newEnd) {
			final EventData data = getData(event);
			data.setEndTime(newEnd);
		}

		public void setStartTime(Object event, Date newStart) {
			final EventData data = getData(event);
			data.setStartTime(newStart);
		}

		public int getGridSize() {
			return 15;
		}

		public boolean isWholeDayEvent(Object event) {
			final EventData data = getData(event);
			if (data != null) {
				return data.isAlldayEvent();
			} else {
				Window.alert("Error: " + event);
				return true;
			}
		}

		public EventPanel createPanel(Object newData, int viewType) {
			final EventData data = getData(newData);
			if (data.isAlldayEvent()) {
				WholeDayField panel = new WholeDayField(this);
				panel.setData(newData);
				return panel;
			} else {

				switch (viewType) {
				case DatePanel.MONTH: {
					final MonthField panel = new MonthField(this);
					panel.setData(newData);
					return panel;

				}
				case DatePanel.WEEK: {
					final DayField panel = new DayField(this);
					panel.setData(newData);
					return panel;
				}
				case DatePanel.DAY: {
					final DayField panel = new DayField(this);
					panel.setData(newData);
					return panel;
				}
				default: {
					final DayField panel = new DayField(this);
					panel.setData(newData);
					return panel;
				}
				}
			}
		}

		public int getHalfHeight() {
			return 20;
		}

		public boolean showHalfHour() {
			return false;
		}

		public boolean useShowMore() {
			return true;
		}

		public int getSingleTopHeight() {
			return 0;
		}
	}

	public class SampleEventCacheController implements EventController {

		private HashMap<String, EventData> items = new HashMap<String, EventData>();

		protected Date startRange = null, endRange = null;

		public SampleEventCacheController() {
			super();
		}

		public void getEventsForRange(Date start, Date end,
				final MultiView caller, final boolean doRefresh) {
			startRange = start;
			endRange = end;
			JsonUtil.debug("getEventsForRange: start = "
					+ startRange.toString() + ", end = " + endRange.toString());
			if (Util.getProgramMode() == ProgramMode.STUBBED) {

			} else if (Util.getProgramMode() == ProgramMode.JSONRPC) {
				// JSON-RPC
				String[] params = { dateToSql(start), dateToSql(end),
						state.getDefaultProvider().toString() };
				RequestBuilder builder = new RequestBuilder(
						RequestBuilder.POST,
						URL
								.encode(Util
										.getJsonRequest(
												"org.freemedsoftware.api.Scheduler.GetDailyAppointmentsRange",
												params)));
				try {
					builder.sendRequest(null, new RequestCallback() {
						public void onError(Request request, Throwable ex) {
							state.getToaster().addItem("Scheduler",
									"Failed to get scheduler items.",
									Toaster.TOASTER_ERROR);
						}

						@SuppressWarnings("unchecked")
						public void onResponseReceived(Request request,
								Response response) {
							if (200 == response.getStatusCode()) {
								if (response.getText().compareToIgnoreCase(
										"false") != 0) {
									HashMap<String, String>[] r = (HashMap<String, String>[]) JsonUtil
											.shoehornJson(JSONParser
													.parse(response.getText()),
													"HashMap<String,String>[]");
									if (r != null) {
										if (r.length > 0) {
											JsonUtil.debug("found " + r.length
													+ " events");
											List<EventData> e = new ArrayList<EventData>();
											for (int iter = 0; iter < r.length; iter++) {
												JsonUtil.debug("Iterating at "
														+ iter);
												EventData d = shoehornEventData(r[iter]);
												JsonUtil.debug("Found: "
														+ d.toString());
												e.add(d);
											}
											JsonUtil
													.debug("calling populateEvents");
											populateEvents(
													(ArrayList<EventData>) e,
													startRange, endRange,
													caller, doRefresh);
										}
									}
								} else {
									JsonUtil
											.debug("Received dummy response from JSON backend");
								}
							} else {
								state.getToaster().addItem("Scheduler",
										"Failed to get scheduler items.",
										Toaster.TOASTER_ERROR);
							}
						}
					});
				} catch (RequestException e) {
					state.getToaster().addItem("Scheduler",
							"Failed to get scheduler items.",
							Toaster.TOASTER_ERROR);
				}
			} else {
				// GWT-RPC
			}
		}

		/**
		 * Shoehorn string representations of date and time into a
		 * java.util.Date object.
		 * 
		 * @param date
		 *            SQL format date (YYYY-MM-DD)
		 * @param hour
		 *            Hour (24 hour format)
		 * @param minute
		 *            Minute
		 * @return
		 */
		public Calendar importSqlDateTime(String date, String hour,
				String minute) {
			Calendar calendar = new GregorianCalendar();
			calendar.set(Calendar.YEAR, Integer.parseInt(date.substring(0, 4)));
			calendar.set(Calendar.MONTH,
					Integer.parseInt(date.substring(5, 7)) - 1);
			calendar
					.set(Calendar.DATE, Integer.parseInt(date.substring(8, 10)));

			calendar.set(Calendar.HOUR_OF_DAY, Integer.parseInt(hour));
			calendar.set(Calendar.MINUTE, Integer.parseInt(minute));

			calendar.set(Calendar.SECOND, 0);
			calendar.set(Calendar.MILLISECOND, 0);

			return calendar;
		}

		public Date shoehornSqlDateTime(String date, String hour, String minute) {
			Calendar x = importSqlDateTime(date, hour, minute);
			return new Date(x.getTime().getTime());
		}

		public EventData shoehornEventData(HashMap<String, String> o) {
			EventData data = new EventData();

			// Set date / time information
			Calendar cal = importSqlDateTime(o.get("date_of"), o.get("hour"), o
					.get("minute"));
			data.setStartTime(new Date(cal.getTime().getTime()));
			cal.add(Calendar.MINUTE, Integer.parseInt(o.get("duration")));
			data.setEndTime(new Date(cal.getTime().getTime()));

			// Set patient and other appointment information
			data.setPatientId(Integer.parseInt(o.get("patient_id")));
			data.setProviderId(Integer.parseInt(o.get("provider_id")));
			data.setPatientName(o.get("patient"));
			data.setProviderName(o.get("provider"));

			// Set event label
			data.setData(o.get("patient") + ": " + o.get("note"));

			return data;
		}

		public void populateEvents(ArrayList<EventData> data, Date start,
				Date end, MultiView caller, boolean doRefresh) {
			JsonUtil.debug("populateEvents for " + data.size() + " events");
			JsonUtil.debug("start = " + start.toString() + ", end = "
					+ end.toString());
			ArrayList<EventData> found = new ArrayList<EventData>();
			Iterator<EventData> walker = data.iterator();
			while (walker.hasNext()) {
				EventData thisData = (EventData) walker.next();
				JsonUtil.debug("Iterating through event data");
				if ((thisData.getStartTime().after(start) && thisData
						.getStartTime().before(end))
						|| DateUtils.isSameDay(thisData.getStartTime(), start)
						|| DateUtils.isSameDay(thisData.getStartTime(), end)) {
					JsonUtil.debug("event " + thisData.toString() + " added");
					found.add(thisData);
				}
			}
			caller.setEvents((Serializable[]) found
					.toArray(new Serializable[0]));
		}

		public void updateEvent(Object updated) {
			removeEvent(updated);
			addEvent(updated);
		}

		public void removeEvent(Object updated) {
			EventData data = (EventData) updated;
			// Window.alert("Remove" + items.size());
			items.remove(data.getIdentifier());
			// Window.alert("Remove" + items.size());
		}

		public void addEvent(Object updated) {
			EventData data = (EventData) updated;
			items.put(data.getIdentifier(), (EventData) updated);
		}

	}

	protected CurrentState state = null;

	private Label label = new Label("Feedback");

	private MultiView schedulerContainerPanel = null;

	protected DockPanel panel = new DockPanel();

	public SchedulerWidget() {
		super();
		init(null);
	}

	public SchedulerWidget(CurrentState s) {
		super();
		JsonUtil.debug("Initializing scheduler widget with state passed");
		init(s);
	}

	public void init(CurrentState s) {
		state = s;
		initWidget(panel);

		schedulerContainerPanel = new MultiView(
				(EventController) new SampleEventCacheController(),
				(DateRenderer) new StringPanelRenderer());

		final HorizontalPanel fields = new HorizontalPanel();
		panel.add(fields, DockPanel.NORTH);
		fields.add(label);
		fields.setCellHeight(label, "20px");

		panel.add(schedulerContainerPanel, DockPanel.CENTER);
		schedulerContainerPanel.setWidth("100%");
		int height = Window.getClientHeight();
		int shortcutHeight = height - 160;
		if (shortcutHeight < 1) {
			shortcutHeight = 1;
		}
		super.setStyleName("whiteForDemo");
		schedulerContainerPanel.setHeight(shortcutHeight);
		schedulerContainerPanel.addDateListener(this);
		// setGrid.addClickListener(this);
		Window.addWindowResizeListener(this);
	}

	public void setCurrentState(CurrentState s) {
		state = s;
	}

	/**
	 * Convert Date object into minutes from beginning of day.
	 * 
	 * @param d
	 * @return
	 */
	protected int dateToMinutes(Date d) {
		Calendar cal = new GregorianCalendar();
		cal.setTime(d);
		return (cal.get(Calendar.HOUR_OF_DAY) * 60) + cal.get(Calendar.MINUTE);
	}

	protected String dateToSql(Date d) {
		DateTimeFormat ymdFormat = DateTimeFormat.getFormat("yyyy-MM-dd");
		return ymdFormat.format(d);
	}

	public void handleDateEvent(DateEvent newEvent) {
		// Figure out common things
		final EventData data = (EventData) newEvent.getData();
		final int duration = dateToMinutes(data.getEndTime())
				- dateToMinutes(data.getStartTime());

		if (newEvent.getCommand() == DateEventActions.ADD) {
			label
					.setText("Added event on " + data.getStartTime()
							+ ", duration " + new Integer(duration).toString()
							+ " min");
		} else if (newEvent.getCommand() == DateEventActions.UPDATE) {
			label
					.setText("Updated event on " + data.getStartTime()
							+ ", duration " + new Integer(duration).toString()
							+ " min");
		} else if (newEvent.getCommand() == DateEventActions.REMOVE) {
			label
					.setText("Removed event on " + data.getStartTime()
							+ ", duration " + new Integer(duration).toString()
							+ " min");
		} else if (newEvent.getCommand() == DateEventActions.DRAG_DROP) {
			label.setText("TODO: Moved event on " + data.getStartTime() + " - "
					+ data.getEndTime());
		}
	}

	public void onWindowResized(int _int, int _int1) {
		final int height = Window.getClientHeight();
		int shortcutHeight = height - 160;
		if (shortcutHeight < 1) {
			shortcutHeight = 1;
		}
		schedulerContainerPanel.setHeight(shortcutHeight);
	}

	public void onClick(Widget arg0) {
		// demoPanel.setG
	}

}